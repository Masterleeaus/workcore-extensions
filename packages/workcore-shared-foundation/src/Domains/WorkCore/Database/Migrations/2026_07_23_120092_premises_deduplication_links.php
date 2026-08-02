<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pass 35 Stage D — Premises de-duplication.
 *
 * WorkCore is the single authority for jobs (tz_work_orders) and contacts
 * (tz_customer_contacts). The premises domain must not own a second job or
 * contact identity. This migration:
 *
 *  1. Creates pm_premises_work_order_links — typed references from a premises
 *     property to canonical WorkCore work orders.
 *  2. Creates pm_premises_contact_links — typed references from a premises
 *     property to canonical WorkCore customer contacts, with a premises role.
 *  3. Adds premises_id to pm_properties, linking each property to the
 *     canonical tz_premises head record (Stage D head-record linkage; full
 *     column consolidation tracked for a later pass).
 *  4. Drops pm_property_jobs and pm_property_contacts if they exist
 *     (fresh installs never create them; upgraded installs must run the
 *     data backfill in docs/reports/passes/PASS35D notes BEFORE this runs).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_premises_work_order_links')) {
            Schema::create('pm_premises_work_order_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('property_id')->constrained('pm_properties')->cascadeOnDelete();
                $table->foreignId('work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
                $table->string('link_role', 50)->default('service');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['property_id', 'work_order_id']);
                $table->index(['company_id', 'property_id']);
            });
        }

        if (! Schema::hasTable('pm_premises_contact_links')) {
            Schema::create('pm_premises_contact_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('property_id')->constrained('pm_properties')->cascadeOnDelete();
                $table->foreignId('customer_contact_id')->constrained('tz_customer_contacts')->cascadeOnDelete();
                $table->string('link_role', 50)->default('contact');
                $table->boolean('is_primary')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['property_id', 'customer_contact_id', 'link_role'], 'pm_contact_link_role_uq');
                $table->index(['company_id', 'property_id']);
            });
        }

        if (Schema::hasTable('pm_properties') && ! Schema::hasColumn('pm_properties', 'premises_id')) {
            Schema::table('pm_properties', function (Blueprint $table): void {
                $table->foreignId('premises_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('tz_premises')
                    ->nullOnDelete();
                $table->index(['company_id', 'premises_id']);
            });
        }

        Schema::dropIfExists('pm_property_jobs');
        Schema::dropIfExists('pm_property_contacts');
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_premises_work_order_links');
        Schema::dropIfExists('pm_premises_contact_links');

        if (Schema::hasTable('pm_properties') && Schema::hasColumn('pm_properties', 'premises_id')) {
            Schema::table('pm_properties', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('premises_id');
            });
        }
        // pm_property_jobs / pm_property_contacts are not recreated; they are
        // retired identities. Restore from the pre-merge ManagedPremises
        // archive if genuinely required.
    }
};

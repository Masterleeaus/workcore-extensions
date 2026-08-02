<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Pass 35 Stage E — premises no longer own form/checklist or asset identity.
        // These tables establish typed links to canonical WorkCore Forms and Assets.

        Schema::create('pm_premises_form_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('pm_properties')->cascadeOnDelete();
            $table->foreignId('form_template_id')->constrained('tz_form_templates')->cascadeOnDelete();
            $table->string('link_role', 50)->default('inspection');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'form_template_id']);
            $table->index(['company_id', 'link_role']);
        });

        Schema::create('pm_premises_asset_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('pm_properties')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->string('location_code', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'asset_id']);
            $table->index(['company_id']);
        });

        // Track which checklists originated from premises, to enable data backfill later.
        if (Schema::hasColumn('tz_form_templates', 'source_type')) {
            // Already exists from Forms module
        } else {
            Schema::table('tz_form_templates', function (Blueprint $table): void {
                $table->string('source_type', 50)->nullable()->after('applies_to');
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
                $table->index(['source_type', 'source_id']);
            });
        }

        // Mark premises checklists/assets non-authoritative via config flag
        // (this is already done in Pass 35D for inspections; same pattern here)
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_premises_asset_links');
        Schema::dropIfExists('pm_premises_form_links');

        if (Schema::hasColumn('tz_form_templates', 'source_type')) {
            Schema::table('tz_form_templates', function (Blueprint $table): void {
                $table->dropIndex(['source_type', 'source_id']);
                $table->dropColumn(['source_type', 'source_id']);
            });
        }
    }
};

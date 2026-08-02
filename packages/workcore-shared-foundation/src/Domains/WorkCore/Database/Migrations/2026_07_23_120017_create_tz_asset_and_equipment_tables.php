<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_asset_categories', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tz_asset_categories')->nullOnDelete();
            $table->string('name', 150);
            $table->string('code', 100);
            $table->string('asset_scope', 40)->default('general');
            $table->text('description')->nullable();
            $table->boolean('requires_serial_number')->default(false);
            $table->boolean('requires_service_schedule')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code'], 'tz_asset_category_company_code_unique');
            $table->index(['company_id', 'asset_scope', 'active']);
        });

        Schema::create('tz_assets', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('tz_asset_categories')->nullOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->foreignId('parent_asset_id')->nullable()->constrained('tz_assets')->nullOnDelete();
            $table->foreignId('custodian_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->string('asset_number', 100);
            $table->string('name', 200);
            $table->string('asset_type', 50)->default('equipment');
            $table->string('ownership_type', 40)->default('company_owned');
            $table->string('status', 30)->default('active');
            $table->string('condition', 30)->default('good');
            $table->string('criticality', 30)->default('standard');
            $table->string('manufacturer', 150)->nullable();
            $table->string('model', 150)->nullable();
            $table->string('serial_number', 150)->nullable();
            $table->string('barcode', 150)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->string('location_description', 255)->nullable();
            $table->date('installed_on')->nullable();
            $table->date('acquired_on')->nullable();
            $table->decimal('purchase_cost', 14, 2)->nullable();
            $table->date('warranty_expires_on')->nullable();
            $table->timestamp('last_service_at')->nullable();
            $table->date('next_service_due_on')->nullable();
            $table->timestamp('last_inspection_at')->nullable();
            $table->date('next_inspection_due_on')->nullable();
            $table->json('specifications')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'asset_number'], 'tz_asset_company_number_unique');
            $table->index(['company_id', 'status', 'asset_type']);
            $table->index(['company_id', 'premises_id', 'status']);
            $table->index(['company_id', 'next_service_due_on']);
            $table->index(['company_id', 'next_inspection_due_on']);
        });

        Schema::create('tz_asset_assignments', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->foreignId('target_asset_id')->nullable()->constrained('tz_assets')->nullOnDelete();
            $table->string('assignment_type', 40);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->text('reason')->nullable();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'asset_id', 'active']);
            $table->index(['company_id', 'premises_id', 'active']);
            $table->index(['company_id', 'worker_id', 'active']);
        });

        Schema::create('tz_asset_maintenance_records', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('tz_appointments')->nullOnDelete();
            $table->foreignId('performed_by_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->string('maintenance_type', 40);
            $table->string('status', 30)->default('scheduled');
            $table->string('vendor_name', 200)->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->text('findings')->nullable();
            $table->text('actions_taken')->nullable();
            $table->date('next_due_on')->nullable();
            $table->json('evidence')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'asset_id', 'status']);
            $table->index(['company_id', 'scheduled_for']);
        });

        Schema::create('tz_asset_meter_readings', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->string('reading_type', 60);
            $table->decimal('reading_value', 18, 4);
            $table->string('unit', 40);
            $table->timestamp('read_at');
            $table->string('source', 50)->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'asset_id', 'reading_type', 'read_at'], 'tz_asset_meter_company_asset_type_idx');
        });

        Schema::create('tz_asset_status_history', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('from_condition', 30)->nullable();
            $table->string('to_condition', 30)->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['company_id', 'asset_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_asset_status_history');
        Schema::dropIfExists('tz_asset_meter_readings');
        Schema::dropIfExists('tz_asset_maintenance_records');
        Schema::dropIfExists('tz_asset_assignments');
        Schema::dropIfExists('tz_assets');
        Schema::dropIfExists('tz_asset_categories');
    }
};

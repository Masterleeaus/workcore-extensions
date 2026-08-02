<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_trade_compliance_profiles', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('business_line_id')->nullable()->constrained('tz_business_lines')->nullOnDelete();
            $table->string('vertical_key', 100);
            $table->string('service_code', 120)->nullable();
            $table->string('jurisdiction', 80)->default('AU-VIC');
            $table->string('name', 200);
            $table->string('status', 30)->default('active');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->json('requirements');
            $table->unsignedInteger('default_warranty_days')->nullable();
            $table->json('settings')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'vertical_key', 'status'], 'tz_trade_profile_vertical_idx');
            $table->index(['company_id', 'business_line_id', 'service_code'], 'tz_trade_profile_scope_idx');
            $table->index(['company_id', 'effective_from', 'effective_to'], 'tz_trade_profile_dates_idx');
        });

        Schema::create('tz_work_order_compliance_items', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained('tz_trade_compliance_profiles')->nullOnDelete();
            $table->string('requirement_code', 120);
            $table->string('requirement_type', 40);
            $table->string('label', 220);
            $table->boolean('required')->default(true);
            $table->string('status', 30)->default('pending');
            $table->string('satisfied_by_type', 60)->nullable();
            $table->char('satisfied_by_public_id', 26)->nullable();
            $table->timestamp('satisfied_at')->nullable();
            $table->foreignId('satisfied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->foreignId('waived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'work_order_id', 'requirement_code'], 'tz_wo_compliance_requirement_uq');
            $table->index(['company_id', 'work_order_id', 'required', 'status'], 'tz_wo_compliance_gate_idx');
        });

        Schema::create('tz_work_order_permits', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
            $table->foreignId('compliance_item_id')->nullable()->constrained('tz_work_order_compliance_items')->nullOnDelete();
            $table->string('permit_type', 100);
            $table->string('permit_number', 160)->nullable();
            $table->string('authority', 200)->nullable();
            $table->string('jurisdiction', 80)->nullable();
            $table->string('status', 30)->default('approved');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('conditions')->nullable();
            $table->string('document_path', 500)->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'work_order_id', 'status'], 'tz_wo_permit_status_idx');
        });

        Schema::create('tz_work_order_safety_controls', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
            $table->foreignId('compliance_item_id')->nullable()->constrained('tz_work_order_compliance_items')->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->string('control_type', 100);
            $table->string('status', 30)->default('completed');
            $table->timestamp('completed_at')->nullable();
            $table->json('details')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'work_order_id', 'control_type'], 'tz_wo_safety_control_idx');
        });

        Schema::create('tz_work_order_test_results', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
            $table->foreignId('compliance_item_id')->nullable()->constrained('tz_work_order_compliance_items')->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->foreignId('trade_licence_id')->nullable()->constrained('tz_trade_licences')->nullOnDelete();
            $table->foreignId('calibration_credential_id')->nullable()->constrained('tz_worker_certifications')->nullOnDelete();
            $table->string('test_type', 120);
            $table->string('result', 30);
            $table->decimal('numeric_value', 18, 6)->nullable();
            $table->string('unit', 40)->nullable();
            $table->json('readings')->nullable();
            $table->string('instrument_identifier', 160)->nullable();
            $table->timestamp('tested_at');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'work_order_id', 'test_type'], 'tz_wo_test_type_idx');
        });

        Schema::create('tz_work_order_evidence', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
            $table->foreignId('compliance_item_id')->nullable()->constrained('tz_work_order_compliance_items')->nullOnDelete();
            $table->foreignId('form_submission_id')->nullable()->constrained('tz_form_submissions')->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->string('evidence_type', 80);
            $table->string('phase', 30)->default('after');
            $table->string('storage_path', 500)->nullable();
            $table->string('content_hash', 128)->nullable();
            $table->timestamp('captured_at');
            $table->json('metadata')->nullable();
            $table->foreignId('captured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'work_order_id', 'phase'], 'tz_wo_evidence_phase_idx');
        });

        Schema::create('tz_work_order_warranties', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
            $table->string('warranty_type', 40)->default('workmanship');
            $table->string('status', 30)->default('active');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('terms')->nullable();
            $table->json('covered_items')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'work_order_id', 'status'], 'tz_wo_warranty_status_idx');
            $table->index(['company_id', 'ends_on', 'status'], 'tz_wo_warranty_expiry_idx');
        });

        Schema::create('tz_work_order_callbacks', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('source_work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
            $table->foreignId('callback_work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
            $table->foreignId('warranty_id')->nullable()->constrained('tz_work_order_warranties')->nullOnDelete();
            $table->string('reason_code', 80);
            $table->text('reason')->nullable();
            $table->boolean('chargeable')->default(false);
            $table->string('status', 30)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'source_work_order_id', 'callback_work_order_id'], 'tz_wo_callback_pair_uq');
            $table->index(['company_id', 'source_work_order_id', 'status'], 'tz_wo_callback_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_work_order_callbacks');
        Schema::dropIfExists('tz_work_order_warranties');
        Schema::dropIfExists('tz_work_order_evidence');
        Schema::dropIfExists('tz_work_order_test_results');
        Schema::dropIfExists('tz_work_order_safety_controls');
        Schema::dropIfExists('tz_work_order_permits');
        Schema::dropIfExists('tz_work_order_compliance_items');
        Schema::dropIfExists('tz_trade_compliance_profiles');
    }
};

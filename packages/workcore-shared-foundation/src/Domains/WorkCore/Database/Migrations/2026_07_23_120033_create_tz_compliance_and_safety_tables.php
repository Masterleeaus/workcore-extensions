<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tz_compliance_requirements', function (Blueprint $t): void {
            $t->id(); $t->ulid('public_id')->unique(); $t->unsignedBigInteger('company_id')->index();
            $t->string('code',64); $t->string('name'); $t->text('description')->nullable();
            $t->string('category',64)->default('general')->index(); $t->char('jurisdiction_country',2)->default('AU');
            $t->string('jurisdiction_region',64)->nullable(); $t->string('authority_name')->nullable();
            $t->string('applies_to',32)->default('premises'); $t->unsignedSmallInteger('frequency_months')->nullable();
            $t->boolean('evidence_required')->default(true); $t->boolean('is_active')->default(true)->index();
            $t->json('metadata')->nullable(); $t->unsignedBigInteger('created_by_user_id'); $t->timestamps(); $t->softDeletes();
            $t->unique(['company_id','code']); $t->index(['company_id','jurisdiction_country','jurisdiction_region'], 'tz_compliance_jurisdiction_idx');
        });
        Schema::create('tz_compliance_obligations', function (Blueprint $t): void {
            $t->id(); $t->ulid('public_id')->unique(); $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('requirement_id')->index(); $t->string('subject_type',32)->default('company')->index();
            $t->unsignedBigInteger('premises_id')->nullable()->index(); $t->unsignedBigInteger('asset_id')->nullable()->index();
            $t->unsignedBigInteger('worker_id')->nullable()->index(); $t->unsignedBigInteger('responsible_worker_id')->nullable()->index();
            $t->unsignedBigInteger('form_submission_id')->nullable()->index(); $t->string('status',32)->default('due')->index();
            $t->date('due_on')->nullable()->index(); $t->date('completed_on')->nullable(); $t->timestamp('last_checked_at')->nullable();
            $t->text('notes')->nullable(); $t->json('metadata')->nullable(); $t->unsignedBigInteger('created_by_user_id');
            $t->timestamps(); $t->softDeletes(); $t->index(['company_id','status','due_on']);
        });
        Schema::create('tz_compliance_certificates', function (Blueprint $t): void {
            $t->id(); $t->ulid('public_id')->unique(); $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('obligation_id')->index(); $t->unsignedBigInteger('premises_id')->nullable()->index();
            $t->unsignedBigInteger('asset_id')->nullable()->index(); $t->unsignedBigInteger('worker_id')->nullable()->index();
            $t->string('certificate_type',100); $t->string('certificate_number')->nullable(); $t->string('issuer')->nullable();
            $t->date('issued_on')->nullable(); $t->date('expires_on')->nullable()->index(); $t->string('status',32)->default('valid')->index();
            $t->string('document_path',2048)->nullable(); $t->text('notes')->nullable(); $t->json('metadata')->nullable();
            $t->unsignedBigInteger('created_by_user_id'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('tz_hazards', function (Blueprint $t): void {
            $t->id(); $t->ulid('public_id')->unique(); $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('premises_id')->nullable()->index(); $t->unsignedBigInteger('asset_id')->nullable()->index();
            $t->unsignedBigInteger('work_order_id')->nullable()->index(); $t->unsignedBigInteger('form_failure_id')->nullable()->index();
            $t->string('title'); $t->text('description'); $t->string('category',64)->default('general')->index();
            $t->string('severity',32)->default('medium')->index(); $t->unsignedTinyInteger('likelihood')->default(1);
            $t->unsignedSmallInteger('risk_score')->default(2)->index(); $t->string('status',32)->default('open')->index();
            $t->timestamp('identified_at'); $t->timestamp('controlled_at')->nullable(); $t->timestamp('resolved_at')->nullable();
            $t->unsignedBigInteger('owner_worker_id')->nullable()->index(); $t->text('controls')->nullable(); $t->json('metadata')->nullable();
            $t->unsignedBigInteger('created_by_user_id'); $t->timestamps(); $t->softDeletes();
            $t->index(['company_id','status','severity']);
        });
        Schema::create('tz_safety_incidents', function (Blueprint $t): void {
            $t->id(); $t->ulid('public_id')->unique(); $t->unsignedBigInteger('company_id')->index(); $t->string('incident_number',64);
            $t->unsignedBigInteger('premises_id')->nullable()->index(); $t->unsignedBigInteger('asset_id')->nullable()->index();
            $t->unsignedBigInteger('worker_id')->nullable()->index(); $t->unsignedBigInteger('work_order_id')->nullable()->index();
            $t->string('incident_type',64)->default('safety')->index(); $t->string('severity',32)->default('medium')->index();
            $t->string('status',32)->default('reported')->index(); $t->timestamp('occurred_at'); $t->timestamp('reported_at');
            $t->text('description'); $t->text('immediate_actions')->nullable(); $t->text('investigation_findings')->nullable();
            $t->boolean('injury_occurred')->default(false); $t->boolean('is_reportable')->default(false)->index();
            $t->string('authority_reference')->nullable(); $t->timestamp('resolved_at')->nullable(); $t->json('metadata')->nullable();
            $t->unsignedBigInteger('reported_by_user_id'); $t->timestamps(); $t->softDeletes();
            $t->unique(['company_id','incident_number']); $t->index(['company_id','status','occurred_at']);
        });
        Schema::create('tz_corrective_actions', function (Blueprint $t): void {
            $t->id(); $t->ulid('public_id')->unique(); $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('obligation_id')->nullable()->index(); $t->unsignedBigInteger('hazard_id')->nullable()->index();
            $t->unsignedBigInteger('incident_id')->nullable()->index(); $t->unsignedBigInteger('form_failure_id')->nullable()->index();
            $t->unsignedBigInteger('work_order_id')->nullable()->index(); $t->unsignedBigInteger('assigned_worker_id')->nullable()->index();
            $t->string('title'); $t->text('description')->nullable(); $t->string('priority',32)->default('normal')->index();
            $t->string('status',32)->default('open')->index(); $t->date('due_on')->nullable()->index(); $t->timestamp('completed_at')->nullable();
            $t->string('closure_evidence_path',2048)->nullable(); $t->text('closure_notes')->nullable(); $t->json('metadata')->nullable();
            $t->unsignedBigInteger('created_by_user_id'); $t->timestamps(); $t->softDeletes();
            $t->index(['company_id','status','due_on']);
        });
        Schema::create('tz_compliance_status_history', function (Blueprint $t): void {
            $t->id(); $t->ulid('public_id')->unique(); $t->unsignedBigInteger('company_id')->index();
            $t->string('subject_type',40); $t->unsignedBigInteger('subject_id'); $t->string('from_status',32)->nullable();
            $t->string('to_status',32); $t->text('reason')->nullable(); $t->unsignedBigInteger('actor_user_id');
            $t->timestamp('changed_at'); $t->timestamps(); $t->index(['company_id','subject_type','subject_id'], 'tz_compliance_status_subject_idx');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tz_compliance_status_history'); Schema::dropIfExists('tz_corrective_actions');
        Schema::dropIfExists('tz_safety_incidents'); Schema::dropIfExists('tz_hazards');
        Schema::dropIfExists('tz_compliance_certificates'); Schema::dropIfExists('tz_compliance_obligations');
        Schema::dropIfExists('tz_compliance_requirements');
    }
};

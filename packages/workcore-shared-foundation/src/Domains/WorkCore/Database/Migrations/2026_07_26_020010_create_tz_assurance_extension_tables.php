<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_inspection_templates', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('name', 240);
            $table->text('description')->nullable();
            $table->string('inspection_type', 80)->default('quality');
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->json('applicable_subject_types')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code', 'version'], 'tz_inspection_templates_code_version_uq');
            $table->index(['company_id', 'inspection_type', 'status'], 'tz_inspection_templates_status_idx');
        });

        Schema::create('tz_inspection_template_items', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('tz_inspection_templates')->cascadeOnDelete();
            $table->string('item_code', 100);
            $table->string('label', 240);
            $table->text('guidance')->nullable();
            $table->string('response_type', 40)->default('pass_fail_na');
            $table->boolean('is_required')->default(true);
            $table->decimal('weight', 8, 2)->default(1);
            $table->string('severity', 30)->default('medium');
            $table->string('failure_action', 40)->default('finding');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'template_id', 'item_code'], 'tz_inspection_template_items_code_uq');
            $table->index(['company_id', 'template_id', 'sort_order'], 'tz_inspection_template_items_sort_idx');
        });

        Schema::create('tz_inspections', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('inspection_number', 100);
            $table->foreignId('template_id')->nullable()->constrained('tz_inspection_templates')->nullOnDelete();
            $table->string('inspection_type', 80)->default('quality');
            $table->string('subject_type', 80);
            $table->char('subject_public_id', 26);
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('tz_assets')->nullOnDelete();
            $table->foreignId('inspector_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->foreignId('inspector_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('scheduled');
            $table->string('outcome', 30)->default('pending');
            $table->decimal('score', 7, 2)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'inspection_number'], 'tz_inspections_number_uq');
            $table->index(['company_id', 'status', 'scheduled_at'], 'tz_inspections_schedule_idx');
            $table->index(['company_id', 'subject_type', 'subject_public_id'], 'tz_inspections_subject_idx');
            $table->index(['company_id', 'premises_id', 'work_order_id'], 'tz_inspections_operations_idx');
        });

        Schema::create('tz_inspection_results', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('inspection_id')->constrained('tz_inspections')->cascadeOnDelete();
            $table->foreignId('template_item_id')->nullable()->constrained('tz_inspection_template_items')->nullOnDelete();
            $table->string('item_code', 100);
            $table->string('item_label', 240);
            $table->string('result', 30)->default('pending');
            $table->string('severity', 30)->default('medium');
            $table->decimal('weight', 8, 2)->default(1);
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 18, 4)->nullable();
            $table->json('value_json')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('evidence_count')->default(0);
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'inspection_id', 'item_code'], 'tz_inspection_results_item_uq');
            $table->index(['company_id', 'inspection_id', 'result'], 'tz_inspection_results_outcome_idx');
        });

        Schema::create('tz_assurance_findings', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('inspection_id')->nullable()->constrained('tz_inspections')->nullOnDelete();
            $table->foreignId('inspection_result_id')->nullable()->constrained('tz_inspection_results')->nullOnDelete();
            $table->string('subject_type', 80);
            $table->char('subject_public_id', 26);
            $table->string('finding_type', 80)->default('nonconformance');
            $table->string('title', 240);
            $table->text('description');
            $table->string('severity', 30)->default('medium');
            $table->string('status', 30)->default('open');
            $table->timestamp('due_at')->nullable();
            $table->foreignId('owner_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status', 'severity', 'due_at'], 'tz_assurance_findings_queue_idx');
            $table->index(['company_id', 'subject_type', 'subject_public_id'], 'tz_assurance_findings_subject_idx');
        });

        Schema::create('tz_risk_register', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('subject_type', 80);
            $table->char('subject_public_id', 26);
            $table->string('category', 80);
            $table->string('title', 240);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('likelihood');
            $table->unsignedTinyInteger('consequence');
            $table->unsignedTinyInteger('risk_score');
            $table->string('risk_level', 30);
            $table->string('status', 30)->default('open');
            $table->json('controls')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('review_due_on')->nullable();
            $table->foreignId('assessed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status', 'risk_level'], 'tz_risk_register_level_idx');
            $table->index(['company_id', 'subject_type', 'subject_public_id'], 'tz_risk_register_subject_idx');
            $table->index(['company_id', 'review_due_on', 'status'], 'tz_risk_register_review_idx');
        });

        Schema::table('tz_corrective_actions', function (Blueprint $table): void {
            $table->foreignId('finding_id')->nullable()->constrained('tz_assurance_findings')->nullOnDelete();
            $table->index(['company_id', 'finding_id', 'status'], 'tz_corrective_actions_finding_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tz_corrective_actions', function (Blueprint $table): void {
            $table->dropIndex('tz_corrective_actions_finding_status_idx');
            $table->dropConstrainedForeignId('finding_id');
        });

        Schema::dropIfExists('tz_risk_register');
        Schema::dropIfExists('tz_assurance_findings');
        Schema::dropIfExists('tz_inspection_results');
        Schema::dropIfExists('tz_inspections');
        Schema::dropIfExists('tz_inspection_template_items');
        Schema::dropIfExists('tz_inspection_templates');
    }
};

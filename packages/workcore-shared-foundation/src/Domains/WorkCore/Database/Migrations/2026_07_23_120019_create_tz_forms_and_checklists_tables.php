<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_form_templates', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('code', 100);
            $table->string('name', 200);
            $table->string('form_type', 60)->default('checklist');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 30)->default('draft');
            $table->string('applies_to', 60)->default('general');
            $table->text('description')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code', 'version'], 'tz_form_template_company_code_version_unique');
            $table->index(['company_id', 'form_type', 'status']);
        });

        Schema::create('tz_form_template_sections', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('tz_form_templates')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('instructions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'template_id', 'sort_order']);
        });

        Schema::create('tz_form_template_fields', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('tz_form_templates')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('tz_form_template_sections')->cascadeOnDelete();
            $table->string('field_key', 120);
            $table->string('label', 240);
            $table->string('field_type', 50);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->string('failure_action', 50)->default('flag');
            $table->string('failure_severity', 30)->default('medium');
            $table->timestamps();
            $table->unique(['template_id', 'field_key'], 'tz_form_field_template_key_unique');
            $table->index(['company_id', 'template_id', 'sort_order']);
        });

        Schema::create('tz_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('tz_form_templates')->restrictOnDelete();
            $table->unsignedInteger('template_version');
            $table->string('subject_type', 60)->default('general');
            $table->char('subject_public_id', 26)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('tz_customers')->nullOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('tz_appointments')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('tz_assets')->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->foreignId('roster_shift_id')->nullable()->constrained('tz_roster_shifts')->nullOnDelete();
            $table->foreignId('submitted_by_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->string('status', 30)->default('draft');
            $table->decimal('score', 8, 2)->nullable();
            $table->string('outcome', 40)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('signature_name')->nullable();
            $table->string('signature_path')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status', 'started_at']);
            $table->index(['company_id', 'subject_type', 'subject_public_id']);
            $table->index(['company_id', 'premises_id', 'work_order_id']);
        });

        Schema::create('tz_form_responses', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained('tz_form_submissions')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('tz_form_template_fields')->restrictOnDelete();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 18, 4)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->json('value_json')->nullable();
            $table->string('result', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['submission_id', 'field_id'], 'tz_form_response_submission_field_unique');
            $table->index(['company_id', 'submission_id', 'result']);
        });

        Schema::create('tz_form_evidence', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained('tz_form_submissions')->cascadeOnDelete();
            $table->foreignId('response_id')->nullable()->constrained('tz_form_responses')->cascadeOnDelete();
            $table->string('evidence_type', 30);
            $table->string('storage_path');
            $table->string('filename')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('caption')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'submission_id', 'evidence_type']);
        });

        Schema::create('tz_form_failures', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained('tz_form_submissions')->cascadeOnDelete();
            $table->foreignId('response_id')->constrained('tz_form_responses')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->string('severity', 30)->default('medium');
            $table->text('description');
            $table->string('status', 30)->default('open');
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'status', 'severity']);
            $table->index(['company_id', 'submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_form_failures');
        Schema::dropIfExists('tz_form_evidence');
        Schema::dropIfExists('tz_form_responses');
        Schema::dropIfExists('tz_form_submissions');
        Schema::dropIfExists('tz_form_template_fields');
        Schema::dropIfExists('tz_form_template_sections');
        Schema::dropIfExists('tz_form_templates');
    }
};

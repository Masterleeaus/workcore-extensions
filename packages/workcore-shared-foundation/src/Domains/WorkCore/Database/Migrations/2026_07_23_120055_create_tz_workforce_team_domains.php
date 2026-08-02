<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_workforce_departments', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('parent_department_id')->nullable()->constrained('tz_workforce_departments')->nullOnDelete();
            $table->string('code', 60); $table->string('name', 160); $table->text('description')->nullable(); $table->boolean('active')->default(true);
            $table->foreignId('manager_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete(); $table->timestamps(); $table->softDeletes();
            $table->unique(['company_id', 'code']); $table->index(['company_id', 'active', 'name']);
        });
        Schema::create('tz_workforce_positions', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('tz_workforce_departments')->nullOnDelete(); $table->string('code', 60); $table->string('title', 160);
            $table->string('employment_type', 40)->default('employee'); $table->json('required_skill_public_ids')->nullable(); $table->boolean('active')->default(true); $table->timestamps(); $table->softDeletes();
            $table->unique(['company_id', 'code']); $table->index(['company_id', 'department_id', 'active']);
        });
        Schema::create('tz_workforce_teams', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('tz_workforce_departments')->nullOnDelete(); $table->foreignId('leader_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->string('code', 60); $table->string('name', 160); $table->string('team_type', 50)->default('operational'); $table->boolean('active')->default(true); $table->timestamps(); $table->softDeletes();
            $table->unique(['company_id', 'code']); $table->index(['company_id', 'active', 'team_type']);
        });
        Schema::create('tz_workforce_team_members', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('tz_workforce_teams')->cascadeOnDelete(); $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->string('role', 80)->default('member'); $table->date('starts_on')->nullable(); $table->date('ends_on')->nullable(); $table->timestamps();
            $table->unique(['team_id', 'worker_id', 'starts_on'], 'tz_team_member_period_uq'); $table->index(['company_id', 'worker_id', 'ends_on']);
        });
        Schema::create('tz_worker_employment_records', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('tz_workforce_positions')->nullOnDelete(); $table->foreignId('department_id')->nullable()->constrained('tz_workforce_departments')->nullOnDelete();
            $table->foreignId('manager_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete(); $table->string('state', 40)->default('draft'); $table->string('employment_type', 40)->default('employee');
            $table->date('commenced_on')->nullable(); $table->date('probation_ends_on')->nullable(); $table->date('ended_on')->nullable(); $table->string('pay_basis', 30)->nullable(); $table->decimal('base_rate', 14, 4)->nullable();
            $table->string('award_code', 100)->nullable(); $table->string('classification_code', 100)->nullable(); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
            $table->index(['company_id', 'worker_id', 'state']); $table->index(['company_id', 'department_id', 'state'], 'tz_emp_record_dept_state_idx');
        });
        Schema::create('tz_worker_lifecycle_events', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('employment_record_id')->constrained('tz_worker_employment_records')->cascadeOnDelete();
            $table->string('from_state', 40)->nullable(); $table->string('to_state', 40); $table->text('reason')->nullable(); $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('occurred_at'); $table->json('metadata')->nullable(); $table->timestamps();
            $table->index(['company_id', 'employment_record_id', 'occurred_at'], 'tz_lifecycle_record_time_idx');
        });
        Schema::create('tz_worker_probations', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('employment_record_id')->constrained('tz_worker_employment_records')->cascadeOnDelete();
            $table->date('starts_on'); $table->date('ends_on'); $table->string('status', 30)->default('active'); $table->text('outcome_notes')->nullable(); $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('decided_at')->nullable(); $table->timestamps();
            $table->index(['company_id', 'status', 'ends_on']);
        });
        Schema::create('tz_worker_employment_changes', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('employment_record_id')->constrained('tz_worker_employment_records')->cascadeOnDelete();
            $table->string('change_type', 30); $table->foreignId('from_department_id')->nullable()->constrained('tz_workforce_departments')->nullOnDelete(); $table->foreignId('to_department_id')->nullable()->constrained('tz_workforce_departments')->nullOnDelete();
            $table->foreignId('from_position_id')->nullable()->constrained('tz_workforce_positions')->nullOnDelete(); $table->foreignId('to_position_id')->nullable()->constrained('tz_workforce_positions')->nullOnDelete();
            $table->date('effective_on'); $table->text('reason')->nullable(); $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->index(['company_id', 'change_type', 'effective_on'], 'tz_emp_change_type_date_idx');
        });

        Schema::create('tz_job_vacancies', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('position_id')->nullable()->constrained('tz_workforce_positions')->nullOnDelete();
            $table->string('reference', 80); $table->string('title', 180); $table->text('description')->nullable(); $table->string('status', 30)->default('draft'); $table->unsignedSmallInteger('openings')->default(1);
            $table->date('opens_on')->nullable(); $table->date('closes_on')->nullable(); $table->json('required_skill_public_ids')->nullable(); $table->foreignId('hiring_manager_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->softDeletes(); $table->unique(['company_id', 'reference']); $table->index(['company_id', 'status', 'closes_on']);
        });
        Schema::create('tz_recruitment_stages', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('vacancy_id')->nullable()->constrained('tz_job_vacancies')->cascadeOnDelete();
            $table->string('code', 60); $table->string('name', 120); $table->unsignedSmallInteger('position')->default(0); $table->boolean('terminal')->default(false); $table->timestamps(); $table->unique(['company_id', 'vacancy_id', 'code'], 'tz_recruit_stage_uq');
        });
        Schema::create('tz_job_applications', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('vacancy_id')->constrained('tz_job_vacancies')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('tz_recruitment_stages')->nullOnDelete(); $table->foreignId('hired_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->string('candidate_name', 180); $table->string('candidate_email', 200); $table->string('candidate_phone', 60)->nullable(); $table->string('status', 30)->default('draft');
            $table->string('resume_path', 500)->nullable(); $table->json('answers')->nullable(); $table->timestamp('submitted_at')->nullable(); $table->timestamp('decided_at')->nullable(); $table->text('decision_reason')->nullable(); $table->timestamps(); $table->softDeletes();
            $table->index(['company_id', 'vacancy_id', 'status']); $table->index(['company_id', 'candidate_email']);
        });
        Schema::create('tz_job_application_notes', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('application_id')->constrained('tz_job_applications')->cascadeOnDelete();
            $table->text('note'); $table->boolean('private')->default(true); $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->index(['company_id', 'application_id', 'created_at'], 'tz_job_note_app_time_idx');
        });
        Schema::create('tz_recruitment_interviews', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('application_id')->constrained('tz_job_applications')->cascadeOnDelete();
            $table->timestamp('starts_at'); $table->timestamp('ends_at')->nullable(); $table->string('timezone', 100)->default('Australia/Melbourne'); $table->string('location', 255)->nullable(); $table->string('status', 30)->default('scheduled');
            $table->json('interviewer_user_ids')->nullable(); $table->json('scorecard')->nullable(); $table->text('notes')->nullable(); $table->timestamps(); $table->index(['company_id', 'status', 'starts_at']);
        });
        Schema::create('tz_offer_letters', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('application_id')->constrained('tz_job_applications')->cascadeOnDelete();
            $table->string('status', 30)->default('draft'); $table->date('proposed_start_on')->nullable(); $table->string('pay_basis', 30)->nullable(); $table->decimal('offered_rate', 14, 4)->nullable(); $table->string('currency_code', 3)->default('AUD');
            $table->string('document_path', 500)->nullable(); $table->timestamp('sent_at')->nullable(); $table->timestamp('responded_at')->nullable(); $table->timestamp('expires_at')->nullable(); $table->timestamps(); $table->index(['company_id', 'status', 'expires_at']);
        });

        Schema::create('tz_onboarding_templates', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->string('code', 60); $table->string('name', 160); $table->string('plan_type', 30)->default('onboarding'); $table->boolean('active')->default(true); $table->timestamps(); $table->softDeletes(); $table->unique(['company_id', 'code', 'plan_type'], 'tz_onboard_template_uq');
        });
        Schema::create('tz_onboarding_template_tasks', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('template_id')->constrained('tz_onboarding_templates')->cascadeOnDelete();
            $table->string('task_key', 80); $table->string('title', 180); $table->text('instructions')->nullable(); $table->boolean('required')->default(true); $table->unsignedSmallInteger('due_offset_days')->nullable(); $table->unsignedSmallInteger('position')->default(0); $table->timestamps(); $table->unique(['template_id', 'task_key']);
        });
        Schema::create('tz_worker_onboarding_plans', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete(); $table->foreignId('template_id')->nullable()->constrained('tz_onboarding_templates')->nullOnDelete();
            $table->string('status', 30)->default('draft'); $table->date('starts_on')->nullable(); $table->date('due_on')->nullable(); $table->timestamp('completed_at')->nullable(); $table->foreignId('owner_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete(); $table->timestamps(); $table->softDeletes(); $table->index(['company_id', 'worker_id', 'status']);
        });
        Schema::create('tz_worker_onboarding_tasks', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('plan_id')->constrained('tz_worker_onboarding_plans')->cascadeOnDelete();
            $table->string('task_key', 80); $table->string('title', 180); $table->boolean('required')->default(true); $table->string('status', 30)->default('pending'); $table->date('due_on')->nullable(); $table->timestamp('completed_at')->nullable(); $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->string('evidence_path', 500)->nullable(); $table->timestamps(); $table->unique(['plan_id', 'task_key']);
        });
        Schema::create('tz_worker_offboarding_plans', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete(); $table->foreignId('template_id')->nullable()->constrained('tz_onboarding_templates')->nullOnDelete();
            $table->string('status', 30)->default('draft'); $table->date('last_working_on')->nullable(); $table->timestamp('completed_at')->nullable(); $table->text('reason')->nullable(); $table->timestamps(); $table->softDeletes(); $table->index(['company_id', 'worker_id', 'status']);
        });
        Schema::create('tz_worker_offboarding_tasks', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('plan_id')->constrained('tz_worker_offboarding_plans')->cascadeOnDelete();
            $table->string('task_key', 80); $table->string('title', 180); $table->boolean('required')->default(true); $table->string('status', 30)->default('pending'); $table->timestamp('completed_at')->nullable(); $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->unique(['plan_id', 'task_key']);
        });

        Schema::create('tz_compliance_document_types', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->string('code', 80); $table->string('name', 180); $table->boolean('requires_expiry')->default(false); $table->boolean('required_for_assignment')->default(false); $table->unsignedSmallInteger('expiry_warning_days')->default(30); $table->boolean('active')->default(true); $table->timestamps(); $table->softDeletes(); $table->unique(['company_id', 'code']);
        });
        Schema::create('tz_worker_compliance_documents', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete(); $table->foreignId('document_type_id')->constrained('tz_compliance_document_types')->restrictOnDelete();
            $table->string('status', 30)->default('draft'); $table->string('issuer', 180)->nullable(); $table->string('credential_number', 150)->nullable(); $table->date('issued_on')->nullable(); $table->date('expires_on')->nullable(); $table->string('evidence_path', 500)->nullable(); $table->string('evidence_hash', 128); $table->timestamp('submitted_at')->nullable(); $table->timestamp('verified_at')->nullable(); $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->text('decision_reason')->nullable(); $table->timestamps(); $table->softDeletes(); $table->index(['company_id', 'worker_id', 'status']); $table->index(['company_id', 'expires_on']);
        });
        Schema::create('tz_compliance_verifications', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('document_id')->constrained('tz_worker_compliance_documents')->cascadeOnDelete();
            $table->string('result', 30); $table->text('notes')->nullable(); $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('verified_at'); $table->json('metadata')->nullable(); $table->timestamps(); $table->index(['company_id', 'document_id', 'verified_at'], 'tz_compliance_doc_time_idx');
        });

        Schema::create('tz_performance_cycles', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->string('name', 180); $table->date('starts_on'); $table->date('ends_on'); $table->string('status', 30)->default('draft'); $table->timestamps(); $table->softDeletes(); $table->index(['company_id', 'status', 'starts_on']);
        });
        Schema::create('tz_performance_objectives', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('cycle_id')->nullable()->constrained('tz_performance_cycles')->nullOnDelete(); $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->foreignId('owner_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete(); $table->string('title', 200); $table->text('description')->nullable(); $table->string('status', 30)->default('draft'); $table->unsignedTinyInteger('progress_percent')->default(0); $table->date('due_on')->nullable(); $table->timestamps(); $table->softDeletes(); $table->index(['company_id', 'worker_id', 'status']);
        });
        Schema::create('tz_performance_key_results', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('objective_id')->constrained('tz_performance_objectives')->cascadeOnDelete();
            $table->string('title', 200); $table->string('measure_type', 30)->default('number'); $table->decimal('starting_value', 16, 4)->default(0); $table->decimal('target_value', 16, 4); $table->decimal('current_value', 16, 4)->default(0); $table->string('unit', 40)->nullable(); $table->timestamps(); $table->index(['company_id', 'objective_id']);
        });
        Schema::create('tz_performance_checkins', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('objective_id')->constrained('tz_performance_objectives')->cascadeOnDelete();
            $table->unsignedTinyInteger('progress_percent'); $table->string('health', 30)->default('on_track'); $table->text('note')->nullable(); $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('checked_in_at'); $table->timestamps(); $table->index(['company_id', 'objective_id', 'checked_in_at'], 'tz_perf_checkin_obj_time_idx');
        });
        Schema::create('tz_performance_reviews', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('cycle_id')->nullable()->constrained('tz_performance_cycles')->nullOnDelete(); $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->foreignId('reviewer_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete(); $table->string('status', 30)->default('draft'); $table->decimal('overall_score', 8, 2)->nullable(); $table->json('ratings')->nullable(); $table->text('summary')->nullable(); $table->timestamp('submitted_at')->nullable(); $table->timestamp('acknowledged_at')->nullable(); $table->timestamps(); $table->index(['company_id', 'worker_id', 'status']);
        });
        Schema::create('tz_performance_meetings', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->timestamp('scheduled_at'); $table->string('status', 30)->default('scheduled'); $table->json('attendee_worker_ids')->nullable(); $table->text('agenda')->nullable(); $table->text('notes')->nullable(); $table->timestamps(); $table->index(['company_id', 'worker_id', 'scheduled_at']);
        });
        Schema::create('tz_performance_meeting_actions', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('meeting_id')->constrained('tz_performance_meetings')->cascadeOnDelete();
            $table->string('title', 200); $table->foreignId('owner_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete(); $table->date('due_on')->nullable(); $table->string('status', 30)->default('open'); $table->timestamp('completed_at')->nullable(); $table->timestamps(); $table->index(['company_id', 'status', 'due_on']);
        });

        Schema::create('tz_attendance_policies', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->cascadeOnDelete();
            $table->string('name', 180); $table->boolean('requires_verification')->default(false); $table->unsignedTinyInteger('minimum_factors')->default(0); $table->boolean('allow_offline')->default(false); $table->unsignedSmallInteger('attempt_valid_minutes')->default(15); $table->boolean('active')->default(true); $table->timestamps(); $table->softDeletes(); $table->index(['company_id', 'premises_id', 'active']);
        });
        Schema::create('tz_attendance_policy_methods', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('policy_id')->constrained('tz_attendance_policies')->cascadeOnDelete();
            $table->string('method', 30); $table->boolean('required')->default(true); $table->json('settings')->nullable(); $table->timestamps(); $table->unique(['policy_id', 'method']);
        });
        Schema::create('tz_attendance_geofences', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->cascadeOnDelete();
            $table->string('name', 180); $table->decimal('latitude', 10, 7); $table->decimal('longitude', 10, 7); $table->unsignedInteger('radius_metres')->default(100); $table->boolean('active')->default(true); $table->timestamps(); $table->softDeletes(); $table->index(['company_id', 'premises_id', 'active']);
        });
        Schema::create('tz_attendance_qr_tokens', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->cascadeOnDelete();
            $table->string('token_hash', 128)->unique(); $table->string('token_type', 30)->default('dynamic'); $table->timestamp('valid_from'); $table->timestamp('expires_at'); $table->unsignedInteger('max_uses')->nullable(); $table->unsignedInteger('use_count')->default(0); $table->timestamp('revoked_at')->nullable(); $table->timestamps(); $table->index(['company_id', 'premises_id', 'expires_at']);
        });
        Schema::create('tz_attendance_network_rules', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->cascadeOnDelete();
            $table->string('name', 180); $table->string('cidr', 80); $table->boolean('active')->default(true); $table->timestamps(); $table->softDeletes(); $table->index(['company_id', 'premises_id', 'active']);
        });
        Schema::create('tz_attendance_biometric_credentials', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->string('provider', 80); $table->string('credential_reference', 255); $table->string('template_hash', 128); $table->string('status', 30)->default('active'); $table->timestamp('consented_at')->nullable(); $table->timestamp('revoked_at')->nullable(); $table->timestamps(); $table->unique(['company_id', 'worker_id', 'provider'], 'tz_biometric_worker_provider_uq');
        });
        Schema::create('tz_attendance_verification_attempts', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete(); $table->foreignId('policy_id')->nullable()->constrained('tz_attendance_policies')->nullOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete(); $table->foreignId('attendance_session_id')->nullable(); $table->foreign('attendance_session_id', 'tz_verify_session_fk')->references('id')->on('tz_attendance_sessions')->nullOnDelete();
            $table->string('status', 30)->default('pending'); $table->json('methods_attempted'); $table->json('methods_passed')->nullable(); $table->json('evidence_references')->nullable(); $table->decimal('latitude', 10, 7)->nullable(); $table->decimal('longitude', 10, 7)->nullable(); $table->string('ip_address', 64)->nullable();
            $table->timestamp('attempted_at'); $table->timestamp('expires_at'); $table->timestamp('consumed_at')->nullable(); $table->text('rejection_reason')->nullable(); $table->timestamps(); $table->index(['company_id', 'worker_id', 'status', 'expires_at'], 'tz_verify_worker_status_idx');
        });
        Schema::create('tz_attendance_offline_batches', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->string('device_id', 180); $table->string('status', 30)->default('open'); $table->unsignedInteger('event_count')->default(0); $table->string('batch_hash', 128)->nullable(); $table->timestamp('sealed_at')->nullable(); $table->timestamp('synced_at')->nullable(); $table->text('rejection_reason')->nullable(); $table->timestamps(); $table->unique(['company_id', 'device_id', 'public_id'], 'tz_offline_batch_device_uq'); $table->index(['company_id', 'worker_id', 'status']);
        });
        Schema::create('tz_attendance_offline_events', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('batch_id')->constrained('tz_attendance_offline_batches')->cascadeOnDelete();
            $table->string('device_event_id', 180); $table->string('event_type', 30); $table->timestamp('occurred_at'); $table->string('payload_hash', 128); $table->json('encrypted_payload')->nullable(); $table->string('sync_status', 30)->default('pending'); $table->string('server_record_public_id', 26)->nullable(); $table->timestamps(); $table->unique(['company_id', 'device_event_id']); $table->index(['company_id', 'batch_id', 'sync_status'], 'tz_offline_event_sync_idx');
        });

        Schema::create('tz_pay_codes', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->string('code', 80); $table->string('name', 180); $table->string('category', 30); $table->boolean('taxable')->default(true); $table->boolean('superable')->default(true); $table->boolean('counts_as_gross')->default(true); $table->string('ledger_account_code', 100)->nullable(); $table->boolean('active')->default(true); $table->timestamps(); $table->softDeletes(); $table->unique(['company_id', 'code']);
        });
        Schema::create('tz_payroll_settings', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->string('currency_code', 3)->default('AUD'); $table->string('pay_frequency', 30)->default('fortnightly'); $table->string('timezone', 100)->default('Australia/Melbourne'); $table->json('rules')->nullable(); $table->boolean('active')->default(true); $table->timestamps(); $table->unique('company_id');
        });
        Schema::create('tz_payroll_cycles', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->string('name', 160); $table->string('frequency', 30); $table->date('anchor_date'); $table->boolean('active')->default(true); $table->timestamps(); $table->index(['company_id', 'active']);
        });
        Schema::create('tz_payroll_runs', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('cycle_id')->nullable()->constrained('tz_payroll_cycles')->nullOnDelete();
            $table->string('run_number', 100); $table->date('period_starts_on'); $table->date('period_ends_on'); $table->date('payment_on')->nullable(); $table->string('status', 30)->default('draft'); $table->string('currency_code', 3)->default('AUD');
            $table->bigInteger('gross_minor')->default(0); $table->bigInteger('deduction_minor')->default(0); $table->bigInteger('net_minor')->default(0); $table->timestamp('calculated_at')->nullable(); $table->timestamp('submitted_at')->nullable(); $table->timestamp('approved_at')->nullable(); $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('finalized_at')->nullable(); $table->timestamps(); $table->softDeletes(); $table->unique(['company_id', 'run_number']); $table->index(['company_id', 'status', 'period_starts_on']);
        });
        Schema::create('tz_payroll_run_workers', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('payroll_run_id')->constrained('tz_payroll_runs')->cascadeOnDelete(); $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->foreignId('timesheet_id')->nullable()->constrained('tz_timesheets')->nullOnDelete(); $table->bigInteger('gross_minor')->default(0); $table->bigInteger('deduction_minor')->default(0); $table->bigInteger('net_minor')->default(0); $table->json('calculation_snapshot')->nullable(); $table->timestamps(); $table->unique(['payroll_run_id', 'worker_id']);
        });
        Schema::create('tz_payroll_lines', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('payroll_run_worker_id')->constrained('tz_payroll_run_workers')->cascadeOnDelete(); $table->foreignId('pay_code_id')->nullable()->constrained('tz_pay_codes')->nullOnDelete();
            $table->string('pay_code', 80); $table->string('description', 200)->nullable(); $table->decimal('quantity', 14, 4)->default(1); $table->decimal('rate', 14, 4)->nullable(); $table->bigInteger('amount_minor'); $table->json('source_references')->nullable(); $table->timestamps(); $table->index(['company_id', 'pay_code']);
        });
        Schema::create('tz_payslips', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('payroll_run_worker_id')->constrained('tz_payroll_run_workers')->cascadeOnDelete(); $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->string('status', 30)->default('draft'); $table->string('document_path', 500)->nullable(); $table->string('document_hash', 128)->nullable(); $table->timestamp('issued_at')->nullable(); $table->timestamp('delivered_at')->nullable(); $table->timestamp('acknowledged_at')->nullable(); $table->timestamps(); $table->unique(['payroll_run_worker_id']); $table->index(['company_id', 'worker_id', 'issued_at']);
        });
        Schema::create('tz_payroll_approvals', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('payroll_run_id')->constrained('tz_payroll_runs')->cascadeOnDelete();
            $table->string('decision', 30); $table->text('reason')->nullable(); $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('decided_at'); $table->timestamps(); $table->index(['company_id', 'payroll_run_id', 'decided_at']);
        });
        Schema::create('tz_payroll_period_locks', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->date('period_starts_on'); $table->date('period_ends_on'); $table->string('reason', 255); $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('locked_at'); $table->timestamp('released_at')->nullable(); $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); $table->index(['company_id', 'period_starts_on', 'period_ends_on'], 'tz_payroll_lock_period_idx');
        });
        Schema::create('tz_overtime_policies', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->string('name', 180); $table->unsignedInteger('daily_threshold_minutes')->nullable(); $table->unsignedInteger('weekly_threshold_minutes')->nullable(); $table->decimal('multiplier', 8, 4)->default(1.5); $table->boolean('requires_preapproval')->default(true); $table->boolean('active')->default(true); $table->timestamps(); $table->softDeletes(); $table->index(['company_id', 'active']);
        });
        Schema::create('tz_overtime_requests', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete(); $table->foreignId('policy_id')->nullable()->constrained('tz_overtime_policies')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete(); $table->date('work_date'); $table->unsignedInteger('requested_minutes'); $table->string('status', 30)->default('submitted'); $table->text('reason')->nullable(); $table->timestamp('decided_at')->nullable(); $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->text('decision_reason')->nullable(); $table->timestamps(); $table->index(['company_id', 'worker_id', 'status', 'work_date'], 'tz_overtime_worker_status_idx');
        });
        Schema::create('tz_payroll_journal_exports', function (Blueprint $table): void {
            $table->id(); $table->char('public_id', 26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete(); $table->foreignId('payroll_run_id')->constrained('tz_payroll_runs')->cascadeOnDelete();
            $table->uuid('journal_entry_id')->nullable()->index(); $table->string('status', 30)->default('pending'); $table->string('idempotency_key', 150); $table->json('posting_summary')->nullable(); $table->timestamp('exported_at')->nullable(); $table->text('error_message')->nullable(); $table->timestamps(); $table->unique(['company_id', 'idempotency_key']); $table->index(['company_id', 'payroll_run_id', 'status'], 'tz_payroll_export_run_idx');
        });
    }

    public function down(): void
    {
        foreach ([
            'tz_payroll_journal_exports','tz_overtime_requests','tz_overtime_policies','tz_payroll_period_locks','tz_payroll_approvals','tz_payslips','tz_payroll_lines','tz_payroll_run_workers','tz_payroll_runs','tz_payroll_cycles','tz_payroll_settings','tz_pay_codes',
            'tz_attendance_offline_events','tz_attendance_offline_batches','tz_attendance_verification_attempts','tz_attendance_biometric_credentials','tz_attendance_network_rules','tz_attendance_qr_tokens','tz_attendance_geofences','tz_attendance_policy_methods','tz_attendance_policies',
            'tz_performance_meeting_actions','tz_performance_meetings','tz_performance_reviews','tz_performance_checkins','tz_performance_key_results','tz_performance_objectives','tz_performance_cycles',
            'tz_compliance_verifications','tz_worker_compliance_documents','tz_compliance_document_types',
            'tz_worker_offboarding_tasks','tz_worker_offboarding_plans','tz_worker_onboarding_tasks','tz_worker_onboarding_plans','tz_onboarding_template_tasks','tz_onboarding_templates',
            'tz_offer_letters','tz_recruitment_interviews','tz_job_application_notes','tz_job_applications','tz_recruitment_stages','tz_job_vacancies',
            'tz_worker_employment_changes','tz_worker_probations','tz_worker_lifecycle_events','tz_worker_employment_records','tz_workforce_team_members','tz_workforce_teams','tz_workforce_positions','tz_workforce_departments',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

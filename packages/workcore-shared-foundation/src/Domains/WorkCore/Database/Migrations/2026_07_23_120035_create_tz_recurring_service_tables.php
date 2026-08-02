<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_service_agreements', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('tz_customers')->restrictOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->foreignId('service_id')->constrained('tz_services')->restrictOnDelete();
            $table->foreignId('job_template_id')->nullable()->constrained('tz_job_templates')->nullOnDelete();
            $table->foreignId('source_rebooking_request_id')->nullable()->constrained('tz_rebooking_requests')->nullOnDelete();
            $table->string('agreement_number', 64);
            $table->string('name');
            $table->string('agreement_type', 50)->default('service');
            $table->string('status', 32)->default('draft')->index();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('renewal_type', 32)->default('manual');
            $table->unsignedInteger('renewal_notice_days')->default(30);
            $table->string('timezone', 100)->default('Australia/Melbourne');
            $table->boolean('auto_create_work_orders')->default(true);
            $table->boolean('auto_create_appointments')->default(true);
            $table->unsignedInteger('generation_lead_days')->default(14);
            $table->string('default_priority', 30)->default('normal');
            $table->unsignedInteger('default_duration_minutes')->nullable();
            $table->text('access_notes')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'agreement_number']);
            $table->index(['company_id', 'customer_id', 'premises_id']);
            $table->index(['company_id', 'status', 'starts_on', 'ends_on']);
        });

        Schema::create('tz_recurring_service_rules', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('agreement_id')->constrained('tz_service_agreements')->cascadeOnDelete();
            $table->string('frequency', 32);
            $table->unsignedInteger('interval_count')->default(1);
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->unsignedTinyInteger('month_of_year')->nullable();
            $table->time('time_of_day')->default('09:00:00');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('timezone', 100)->default('Australia/Melbourne');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_run_on')->nullable()->index();
            $table->date('last_generated_on')->nullable();
            $table->unsignedInteger('occurrence_limit')->nullable();
            $table->unsignedInteger('generated_count')->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'agreement_id', 'status']);
        });

        Schema::create('tz_recurring_service_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('agreement_id')->constrained('tz_service_agreements')->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained('tz_recurring_service_rules')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('tz_customers')->restrictOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->foreignId('service_id')->constrained('tz_services')->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('tz_appointments')->nullOnDelete();
            $table->foreignId('original_occurrence_id')->nullable()->constrained('tz_recurring_service_occurrences')->nullOnDelete();
            $table->string('occurrence_key', 120);
            $table->date('local_date');
            $table->timestamp('scheduled_start_at');
            $table->timestamp('scheduled_end_at');
            $table->string('timezone', 100);
            $table->string('status', 32)->default('planned')->index();
            $table->text('exception_reason')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'occurrence_key'], 'tz_rec_occurrence_key_uq');
            $table->index(['company_id', 'agreement_id', 'scheduled_start_at'], 'tz_rec_occ_agreement_start_idx');
            $table->index(['company_id', 'status', 'scheduled_start_at'], 'tz_rec_occ_status_start_idx');
        });

        Schema::create('tz_recurring_service_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('agreement_id')->constrained('tz_service_agreements')->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained('tz_recurring_service_rules')->cascadeOnDelete();
            $table->foreignId('occurrence_id')->nullable()->constrained('tz_recurring_service_occurrences')->nullOnDelete();
            $table->string('exception_type', 32);
            $table->timestamp('original_start_at');
            $table->timestamp('replacement_start_at')->nullable();
            $table->timestamp('replacement_end_at')->nullable();
            $table->text('reason');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'agreement_id', 'original_start_at'], 'tz_rec_exception_agreement_start_idx');
        });

        Schema::create('tz_recurring_service_status_history', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['company_id', 'subject_type', 'subject_id', 'changed_at'], 'tz_rec_status_subject_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_recurring_service_status_history');
        Schema::dropIfExists('tz_recurring_service_exceptions');
        Schema::dropIfExists('tz_recurring_service_occurrences');
        Schema::dropIfExists('tz_recurring_service_rules');
        Schema::dropIfExists('tz_service_agreements');
    }
};

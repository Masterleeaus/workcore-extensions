<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_attendance_sessions', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->foreignId('roster_shift_id')->nullable()->constrained('tz_roster_shifts')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->string('status', 30)->default('clocked_in');
            $table->timestamp('clocked_in_at');
            $table->timestamp('clocked_out_at')->nullable();
            $table->string('timezone', 80)->default('Australia/Melbourne');
            $table->string('source', 40)->default('manual');
            $table->decimal('clock_in_latitude', 10, 7)->nullable();
            $table->decimal('clock_in_longitude', 10, 7)->nullable();
            $table->decimal('clock_out_latitude', 10, 7)->nullable();
            $table->decimal('clock_out_longitude', 10, 7)->nullable();
            $table->unsignedInteger('scheduled_minutes')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('break_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'worker_id', 'clocked_in_at']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'roster_shift_id']);
        });

        Schema::create('tz_attendance_breaks', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('attendance_session_id')->constrained('tz_attendance_sessions')->cascadeOnDelete();
            $table->string('break_type', 40)->default('meal');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->boolean('paid')->default(false);
            $table->text('reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'attendance_session_id', 'started_at']);
        });

        Schema::create('tz_timesheets', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->date('period_starts_on');
            $table->date('period_ends_on');
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'worker_id', 'period_starts_on', 'period_ends_on'], 'tz_timesheet_worker_period_unique');
            $table->index(['company_id', 'status', 'period_starts_on']);
        });

        Schema::create('tz_timesheet_entries', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('timesheet_id')->constrained('tz_timesheets')->cascadeOnDelete();
            $table->foreignId('attendance_session_id')->nullable()->constrained('tz_attendance_sessions')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->date('work_date');
            $table->timestamp('started_at');
            $table->timestamp('ended_at');
            $table->unsignedInteger('worked_minutes');
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('source', 30)->default('attendance');
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['timesheet_id', 'attendance_session_id'], 'tz_timesheet_attendance_unique');
            $table->index(['company_id', 'timesheet_id', 'work_date']);
        });

        Schema::create('tz_leave_types', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name', 160);
            $table->boolean('paid')->default(false);
            $table->string('unit', 20)->default('minutes');
            $table->boolean('requires_document')->default(false);
            $table->unsignedInteger('max_consecutive_days')->nullable();
            $table->boolean('active')->default(true);
            $table->json('settings')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('tz_leave_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('tz_leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('balance_year');
            $table->integer('opening_minutes')->default(0);
            $table->integer('accrued_minutes')->default(0);
            $table->integer('taken_minutes')->default(0);
            $table->integer('adjusted_minutes')->default(0);
            $table->integer('available_minutes')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'worker_id', 'leave_type_id', 'balance_year'], 'tz_leave_balance_unique');
        });

        Schema::create('tz_leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('tz_leave_types')->restrictOnDelete();
            $table->foreignId('replacement_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 80)->default('Australia/Melbourne');
            $table->unsignedInteger('requested_minutes');
            $table->string('status', 30)->default('draft');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('document_path')->nullable();
            $table->boolean('affects_roster')->default(true);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'worker_id', 'starts_at']);
            $table->index(['company_id', 'status', 'starts_at']);
        });

        Schema::create('tz_leave_request_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('leave_request_id')->constrained('tz_leave_requests')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['company_id', 'leave_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_leave_request_history');
        Schema::dropIfExists('tz_leave_requests');
        Schema::dropIfExists('tz_leave_balances');
        Schema::dropIfExists('tz_leave_types');
        Schema::dropIfExists('tz_timesheet_entries');
        Schema::dropIfExists('tz_timesheets');
        Schema::dropIfExists('tz_attendance_breaks');
        Schema::dropIfExists('tz_attendance_sessions');
    }
};

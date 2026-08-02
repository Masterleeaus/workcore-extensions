<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_worker_availability_rules', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->string('availability_type', 30)->default('available');
            $table->string('recurrence_type', 30)->default('weekly');
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('timezone', 100)->default('Australia/Melbourne');
            $table->string('source', 50)->default('manual');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'worker_id', 'weekday']);
            $table->index(['company_id', 'availability_type', 'effective_from', 'effective_to'], 'tz_availability_company_effective_idx');
        });

        Schema::create('tz_rosters', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->string('name', 200);
            $table->string('roster_type', 60)->default('operations');
            $table->string('status', 30)->default('draft');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('timezone', 100)->default('Australia/Melbourne');
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status', 'starts_on', 'ends_on']);
            $table->index(['company_id', 'premises_id', 'starts_on']);
        });

        Schema::create('tz_roster_shifts', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('roster_id')->constrained('tz_rosters')->cascadeOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('tz_appointments')->nullOnDelete();
            $table->string('title', 200);
            $table->string('shift_type', 60)->default('operations');
            $table->string('status', 30)->default('draft');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 100)->default('Australia/Melbourne');
            $table->unsignedSmallInteger('required_worker_count')->default(1);
            $table->json('required_skill_public_ids')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('allow_conflicts')->default(false);
            $table->text('conflict_override_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'roster_id', 'starts_at']);
            $table->index(['company_id', 'premises_id', 'starts_at']);
            $table->index(['company_id', 'status', 'starts_at']);
        });

        Schema::create('tz_roster_shift_assignments', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('roster_shift_id')->constrained('tz_roster_shifts')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->string('assignment_role', 80)->default('worker');
            $table->string('status', 30)->default('assigned');
            $table->timestamp('assigned_at');
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['roster_shift_id', 'worker_id'], 'tz_shift_worker_unique');
            $table->index(['company_id', 'worker_id', 'status']);
        });

        Schema::create('tz_roster_status_history', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('roster_id')->constrained('tz_rosters')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('reason')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['company_id', 'roster_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_roster_status_history');
        Schema::dropIfExists('tz_roster_shift_assignments');
        Schema::dropIfExists('tz_roster_shifts');
        Schema::dropIfExists('tz_rosters');
        Schema::dropIfExists('tz_worker_availability_rules');
    }
};

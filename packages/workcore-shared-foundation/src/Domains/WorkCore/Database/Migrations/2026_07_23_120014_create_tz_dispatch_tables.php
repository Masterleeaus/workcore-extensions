<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_dispatch_assignments', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('dispatch_number', 100);
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('tz_appointments')->nullOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignment_type', 50)->default('job');
            $table->string('priority', 30)->default('normal');
            $table->string('status', 30)->default('assigned');
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->string('timezone', 100)->default('Australia/Melbourne');
            $table->string('route_group', 100)->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->text('instructions')->nullable();
            $table->text('access_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->unsignedBigInteger('travel_distance_meters')->nullable();
            $table->unsignedInteger('travel_duration_seconds')->nullable();
            $table->string('source', 50)->default('manual');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'dispatch_number']);
            $table->index(['company_id', 'status', 'scheduled_start']);
            $table->index(['company_id', 'assigned_user_id', 'scheduled_start']);
            $table->index(['company_id', 'route_group', 'sequence']);
            $table->index(['company_id', 'premises_id', 'scheduled_start']);
            $table->index(['company_id', 'work_order_id']);
            $table->index(['company_id', 'appointment_id']);
        });

        Schema::create('tz_dispatch_events', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('dispatch_assignment_id')->constrained('tz_dispatch_assignments')->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('reason')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['company_id', 'dispatch_assignment_id', 'occurred_at']);
            $table->index(['company_id', 'event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_dispatch_events');
        Schema::dropIfExists('tz_dispatch_assignments');
    }
};

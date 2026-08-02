<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_appointments', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('tz_customers')->nullOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('appointment_type', 50)->default('job');
            $table->string('status', 50)->default('tentative');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 100)->default('Australia/Melbourne');
            $table->boolean('all_day')->default(false);
            $table->text('access_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('source', 50)->default('manual');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status', 'starts_at']);
            $table->index(['company_id', 'appointment_type', 'starts_at']);
            $table->index(['company_id', 'premises_id', 'starts_at']);
            $table->index(['company_id', 'work_order_id']);
        });

        Schema::create('tz_appointment_participants', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained('tz_appointments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('tz_customer_contacts')->nullOnDelete();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('role', 50)->default('attendee');
            $table->string('response_status', 50)->default('needs_action');
            $table->boolean('is_required')->default(false);
            $table->timestamps();
            $table->index(['company_id', 'appointment_id', 'role']);
            $table->index(['company_id', 'user_id', 'appointment_id']);
            $table->index(['company_id', 'contact_id', 'appointment_id']);
        });

        Schema::create('tz_appointment_status_history', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained('tz_appointments')->cascadeOnDelete();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->text('reason')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['company_id', 'appointment_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_appointment_status_history');
        Schema::dropIfExists('tz_appointment_participants');
        Schema::dropIfExists('tz_appointments');
    }
};

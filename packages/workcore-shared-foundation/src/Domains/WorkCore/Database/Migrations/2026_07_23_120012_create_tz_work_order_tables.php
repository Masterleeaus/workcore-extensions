<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_work_orders', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('tz_customers')->restrictOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->string('number', 64);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 30)->default('normal');
            $table->string('status', 50)->default('draft');
            $table->string('source', 50)->default('manual');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status', 'priority']);
            $table->index(['company_id', 'customer_id', 'premises_id']);
        });

        Schema::create('tz_work_order_services', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('tz_services')->nullOnDelete();
            $table->foreignId('job_template_id')->nullable()->constrained('tz_job_templates')->nullOnDelete();
            $table->string('service_code')->nullable();
            $table->string('service_name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 50)->default('pending');
            $table->timestamps();
            $table->index(['company_id', 'work_order_id', 'sort_order']);
        });

        Schema::create('tz_work_order_tasks', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
            $table->foreignId('work_order_service_id')->nullable()->constrained('tz_work_order_services')->cascadeOnDelete();
            $table->foreignId('source_task_template_id')->nullable()->constrained('tz_task_templates')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->boolean('is_required')->default(true);
            $table->string('status', 50)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'work_order_id', 'status']);
            $table->index(['company_id', 'work_order_service_id', 'sort_order']);
        });

        Schema::create('tz_work_order_status_history', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->text('reason')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->index(['company_id', 'work_order_id', 'changed_at']);
        });

        Schema::create('tz_time_entries', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
            $table->foreignId('work_order_task_id')->nullable()->constrained('tz_work_order_tasks')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->text('notes')->nullable();
            $table->boolean('is_billable')->default(true);
            $table->string('status', 50)->default('recorded');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'work_order_id', 'started_at']);
            $table->index(['company_id', 'user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_time_entries');
        Schema::dropIfExists('tz_work_order_status_history');
        Schema::dropIfExists('tz_work_order_tasks');
        Schema::dropIfExists('tz_work_order_services');
        Schema::dropIfExists('tz_work_orders');
    }
};

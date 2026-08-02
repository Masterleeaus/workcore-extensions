<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_support_queues', function (Blueprint $table): void {
            $table->id();$table->char('public_id',26)->unique();$table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('code',60);$table->string('name',160);$table->text('description')->nullable();$table->string('default_priority',30)->default('normal');
            $table->unsignedInteger('first_response_minutes')->default(1440);$table->unsignedInteger('resolution_minutes')->default(5760);$table->json('channel_defaults')->nullable();$table->boolean('active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();$table->timestamps();$table->softDeletes();$table->unique(['company_id','code']);
        });
        Schema::create('tz_support_tickets', function (Blueprint $table): void {
            $table->id();$table->char('public_id',26)->unique();$table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('queue_id')->nullable()->constrained('tz_support_queues')->nullOnDelete();$table->foreignId('customer_id')->nullable()->constrained('tz_customers')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('tz_customer_contacts')->nullOnDelete();$table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();$table->foreignId('appointment_id')->nullable()->constrained('tz_appointments')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('tz_assets')->nullOnDelete();$table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();$table->string('ticket_number',64);$table->string('subject');$table->longText('description');
            $table->string('category',80)->default('general');$table->string('source',50)->default('manual');$table->string('requester_type',40)->default('external');
            $table->string('requester_name',160)->nullable();$table->string('requester_email')->nullable();$table->string('requester_phone',50)->nullable();
            $table->string('priority',30)->default('normal');$table->string('status',40)->default('open');$table->timestamp('first_response_due_at')->nullable();$table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_responded_at')->nullable();$table->timestamp('resolved_at')->nullable();$table->timestamp('closed_at')->nullable();$table->timestamp('last_activity_at')->nullable();
            $table->unsignedTinyInteger('escalation_level')->default(0);$table->boolean('confidential')->default(false);$table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();$table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();$table->timestamps();$table->softDeletes();
            $table->unique(['company_id','ticket_number']);$table->index(['company_id','status','priority']);$table->index(['company_id','assigned_worker_id','status']);$table->index(['company_id','premises_id','status']);
        });
        Schema::create('tz_support_ticket_messages', function (Blueprint $table): void {
            $table->id();$table->char('public_id',26)->unique();$table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();$table->foreignId('support_ticket_id')->constrained('tz_support_tickets')->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();$table->foreignId('author_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();$table->foreignId('author_contact_id')->nullable()->constrained('tz_customer_contacts')->nullOnDelete();
            $table->string('message_type',30)->default('reply');$table->string('visibility',20)->default('public');$table->string('channel',50)->default('internal');$table->longText('body');$table->json('attachments')->nullable();$table->timestamp('sent_at');$table->timestamps();
            $table->index(['company_id','support_ticket_id','sent_at']);
        });
        Schema::create('tz_support_ticket_assignments', function (Blueprint $table): void {
            $table->id();$table->char('public_id',26)->unique();$table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();$table->foreignId('support_ticket_id')->constrained('tz_support_tickets')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('tz_workers')->restrictOnDelete();$table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();$table->timestamp('assigned_at');$table->timestamp('ended_at')->nullable();$table->text('reason')->nullable();$table->boolean('active')->default(true);$table->timestamps();
            $table->index(['company_id','worker_id','active']);
        });
        Schema::create('tz_support_ticket_status_history', function (Blueprint $table): void {
            $table->id();$table->char('public_id',26)->unique();$table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();$table->foreignId('support_ticket_id')->constrained('tz_support_tickets')->cascadeOnDelete();
            $table->string('from_status',40)->nullable();$table->string('to_status',40);$table->text('reason')->nullable();$table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();$table->timestamp('changed_at');$table->timestamp('created_at')->nullable();
            $table->index(['company_id','support_ticket_id','changed_at']);
        });
        Schema::create('tz_support_ticket_sla_events', function (Blueprint $table): void {
            $table->id();$table->char('public_id',26)->unique();$table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();$table->foreignId('support_ticket_id')->constrained('tz_support_tickets')->cascadeOnDelete();
            $table->string('event_type',50);$table->timestamp('occurred_at');$table->json('metadata')->nullable();$table->timestamp('created_at')->nullable();$table->index(['company_id','event_type','occurred_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tz_support_ticket_sla_events');Schema::dropIfExists('tz_support_ticket_status_history');Schema::dropIfExists('tz_support_ticket_assignments');Schema::dropIfExists('tz_support_ticket_messages');Schema::dropIfExists('tz_support_tickets');Schema::dropIfExists('tz_support_queues');
    }
};

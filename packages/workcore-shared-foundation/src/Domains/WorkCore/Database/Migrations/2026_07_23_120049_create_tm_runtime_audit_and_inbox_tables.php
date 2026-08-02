<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tm_audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id',64)->index();
            $table->string('action_key',128);
            $table->string('outcome',32);
            $table->string('actor_type',32);
            $table->string('actor_id',128);
            $table->string('origin',32);
            $table->string('correlation_id',128)->index();
            $table->string('conversation_id',128)->nullable();
            $table->string('workflow_id',128)->nullable();
            $table->string('approval_id',128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['company_id','action_key','occurred_at'],'tm_audit_events_action_time_index');
        });
        Schema::create('tm_external_event_inbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id',64)->index();
            $table->string('source_system',64);
            $table->string('external_event_id',191);
            $table->string('event_type',128);
            $table->char('payload_hash',64);
            $table->string('state',32)->default('received');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('failure_code',128)->nullable();
            $table->timestamps();
            $table->unique(['company_id','source_system','external_event_id'],'tm_external_event_inbox_unique');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tm_external_event_inbox');
        Schema::dropIfExists('tm_audit_events');
    }
};

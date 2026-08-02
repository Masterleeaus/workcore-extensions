<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tm_automation_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('policy_key', 64);
            $table->string('autonomy_level', 32)->default('approve');
            $table->boolean('enabled')->default(true);
            $table->bigInteger('automatic_amount_limit_minor')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->unsignedTinyInteger('automatic_match_threshold')->default(95);
            $table->json('conditions')->nullable();
            $table->json('allowed_actions')->nullable();
            $table->json('blocked_actions')->nullable();
            $table->string('updated_by_actor_type', 32);
            $table->string('updated_by_actor_id', 128);
            $table->timestamps();
            $table->unique(['company_id', 'policy_key'], 'tm_automation_policies_key_unique');
        });

        Schema::create('tm_collection_sequences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('name', 255);
            $table->boolean('is_default')->default(false);
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('throttle_hours')->default(20);
            $table->json('suppressions');
            $table->timestamps();
        });

        Schema::create('tm_collection_sequence_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('collection_sequence_id')->index();
            $table->integer('due_day');
            $table->string('code', 64);
            $table->string('tone', 32);
            $table->json('channels');
            $table->boolean('approval_required')->default(false);
            $table->json('message_policy')->nullable();
            $table->timestamps();
            $table->unique(['collection_sequence_id', 'code'], 'tm_collection_sequence_steps_code_unique');
        });

        Schema::create('tm_scheduled_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('action_key', 128);
            $table->string('entity_type', 64)->nullable();
            $table->string('entity_id', 64)->nullable();
            $table->json('payload');
            $table->timestamp('run_at')->index();
            $table->string('state', 32)->default('scheduled');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('idempotency_key_hash', 64);
            $table->string('workflow_id', 128)->nullable();
            $table->string('correlation_id', 128);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'action_key', 'idempotency_key_hash'], 'tm_scheduled_actions_idempotency_unique');
        });

        Schema::create('tm_action_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('scheduled_action_id')->nullable()->index();
            $table->string('action_key', 128);
            $table->string('state', 32);
            $table->unsignedInteger('attempt_number');
            $table->string('actor_type', 32);
            $table->string('actor_id', 128);
            $table->string('origin', 32);
            $table->string('correlation_id', 128);
            $table->string('error_code', 128)->nullable();
            $table->text('error_message')->nullable();
            $table->json('result_payload')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tm_financial_exceptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('exception_type', 64);
            $table->string('severity', 16);
            $table->string('entity_type', 64)->nullable();
            $table->string('entity_id', 64)->nullable();
            $table->string('state', 32)->default('open');
            $table->json('evidence');
            $table->string('assigned_to_user_id', 64)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by_actor_type', 32)->nullable();
            $table->string('resolved_by_actor_id', 128)->nullable();
            $table->timestamps();
        });

        Schema::create('tm_payment_instruction_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('payment_request_id')->index();
            $table->string('channel', 32);
            $table->string('recipient_hash', 64);
            $table->string('state', 32)->default('queued');
            $table->string('external_message_id', 255)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 128)->nullable();
            $table->timestamps();
        });

        Schema::create('tm_qr_artifacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('payment_request_id')->index();
            $table->string('document_id', 128)->nullable();
            $table->string('content_type', 128);
            $table->char('payload_hash', 64);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'payment_request_id', 'payload_hash'], 'tm_qr_artifacts_payload_unique');
        });

        Schema::create('tm_payment_match_candidates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('payment_evidence_id')->index();
            $table->uuid('invoice_id')->index();
            $table->unsignedTinyInteger('confidence_score');
            $table->string('state', 32)->default('proposed');
            $table->json('reasons');
            $table->boolean('policy_may_auto_confirm')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by_actor_type', 32)->nullable();
            $table->string('reviewed_by_actor_id', 128)->nullable();
            $table->timestamps();
            $table->unique(['payment_evidence_id', 'invoice_id'], 'tm_payment_match_candidates_unique');
        });

        Schema::create('tm_payment_promises', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('invoice_id')->index();
            $table->date('promised_date');
            $table->bigInteger('promised_amount_minor')->nullable();
            $table->char('currency_code', 3);
            $table->string('state', 32)->default('active');
            $table->json('evidence')->nullable();
            $table->timestamps();
        });

        Schema::create('tm_outbox_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('event_type', 128);
            $table->string('aggregate_type', 64);
            $table->string('aggregate_id', 64);
            $table->unsignedInteger('aggregate_version')->default(1);
            $table->json('payload');
            $table->string('actor_type', 32);
            $table->string('actor_id', 128);
            $table->string('origin', 32);
            $table->string('correlation_id', 128);
            $table->string('causation_id', 128)->nullable();
            $table->string('state', 32)->default('pending');
            $table->unsignedInteger('publish_attempts')->default(0);
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'created_at'], 'tm_outbox_events_state_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_outbox_events');
        Schema::dropIfExists('tm_payment_promises');
        Schema::dropIfExists('tm_payment_match_candidates');
        Schema::dropIfExists('tm_qr_artifacts');
        Schema::dropIfExists('tm_payment_instruction_deliveries');
        Schema::dropIfExists('tm_financial_exceptions');
        Schema::dropIfExists('tm_action_attempts');
        Schema::dropIfExists('tm_scheduled_actions');
        Schema::dropIfExists('tm_collection_sequence_steps');
        Schema::dropIfExists('tm_collection_sequences');
        Schema::dropIfExists('tm_automation_policies');
    }
};

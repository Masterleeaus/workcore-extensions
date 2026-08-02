<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tz_action_idempotency', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('action_key', 160);
            $table->string('idempotency_key', 160);
            $table->char('payload_hash', 64);
            $table->string('status', 24);
            $table->longText('result_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'action_key', 'idempotency_key'], 'tz_action_idempotency_unique');
        });

        Schema::create('tz_action_executions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action_key', 160);
            $table->string('risk_level', 16);
            $table->string('source', 48);
            $table->string('confirmation_id', 160)->nullable();
            $table->string('idempotency_key', 160);
            $table->string('correlation_id', 160);
            $table->string('aggregate_type', 80)->nullable();
            $table->ulid('aggregate_public_id')->nullable();
            $table->string('status', 24);
            $table->longText('request_json');
            $table->longText('result_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'action_key', 'created_at'], 'tz_action_exec_company_action_idx');
            $table->index(['company_id', 'correlation_id'], 'tz_action_exec_correlation_idx');
        });

        Schema::create('tz_domain_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('event_id')->unique();
            $table->string('event_name', 160);
            $table->unsignedSmallInteger('event_version')->default(1);
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aggregate_type', 80);
            $table->ulid('aggregate_public_id');
            $table->string('correlation_id', 160);
            $table->string('causation_id', 160)->nullable();
            $table->longText('payload_json');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['company_id', 'aggregate_type', 'aggregate_public_id'], 'tz_domain_event_aggregate_idx');
            $table->index(['company_id', 'correlation_id'], 'tz_domain_event_correlation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_domain_events');
        Schema::dropIfExists('tz_action_executions');
        Schema::dropIfExists('tz_action_idempotency');
    }
};

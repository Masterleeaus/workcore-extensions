<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tm_idempotency_keys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64);
            $table->string('action_key', 128);
            $table->char('idempotency_key_hash', 64);
            $table->char('request_fingerprint', 64);
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->string('created_by_actor_type', 32);
            $table->string('created_by_actor_id', 128);
            $table->string('updated_by_actor_type', 32);
            $table->string('updated_by_actor_id', 128);
            $table->string('correlation_id', 128);
            $table->string('origin', 32);
            $table->json('result_payload')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'action_key', 'idempotency_key_hash'], 'tm_idempotency_company_action_key_unique');
            $table->index(['status', 'expires_at'], 'tm_idempotency_status_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_idempotency_keys');
    }
};

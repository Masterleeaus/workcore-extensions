<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tz_outbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->ulid('message_id')->unique();
            $table->foreignId('company_id')->nullable()->constrained('tz_companies')->cascadeOnDelete();
            $table->string('topic', 160);
            $table->string('event_id', 32)->nullable();
            $table->string('correlation_id', 160);
            $table->string('causation_id', 160)->nullable();
            $table->longText('payload_json');
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at'], 'tz_outbox_ready_idx');
            $table->index(['company_id', 'correlation_id'], 'tz_outbox_company_correlation_idx');
            $table->index('event_id', 'tz_outbox_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_outbox_messages');
    }
};

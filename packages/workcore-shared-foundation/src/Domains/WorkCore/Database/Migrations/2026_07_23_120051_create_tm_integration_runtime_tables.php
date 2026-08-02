<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tm_scheduled_actions', function (Blueprint $table): void {
            $table->char('lease_token', 64)->nullable()->after('attempt_count');
            $table->timestamp('leased_until')->nullable()->index()->after('lease_token');
            $table->string('last_error_code', 128)->nullable()->after('leased_until');
            $table->text('last_error_message')->nullable()->after('last_error_code');
            $table->json('result_payload')->nullable()->after('last_error_message');
        });

        Schema::table('tm_outbox_events', function (Blueprint $table): void {
            $table->char('lease_token', 64)->nullable()->after('publish_attempts');
            $table->timestamp('leased_until')->nullable()->index()->after('lease_token');
            $table->timestamp('next_attempt_at')->nullable()->index()->after('leased_until');
            $table->string('last_error_code', 128)->nullable()->after('next_attempt_at');
            $table->text('last_error_message')->nullable()->after('last_error_code');
            $table->string('external_delivery_id', 255)->nullable()->after('last_error_message');
            $table->timestamp('failed_at')->nullable()->after('published_at');
        });

        Schema::table('tm_external_event_inbox', function (Blueprint $table): void {
            $table->timestamp('processing_lease_until')->nullable()->index()->after('received_at');
            $table->unsignedInteger('attempt_count')->default(0)->after('processing_lease_until');
        });

        Schema::create('tm_bank_imports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('bank_account_id')->index();
            $table->string('source_type', 32)->default('csv');
            $table->char('file_hash', 64)->nullable();
            $table->string('state', 32)->default('processing');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('errors')->nullable();
            $table->string('actor_type', 32);
            $table->string('actor_id', 128);
            $table->string('origin', 32);
            $table->string('correlation_id', 128);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'bank_account_id', 'file_hash'], 'tm_bank_imports_file_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_bank_imports');

        Schema::table('tm_external_event_inbox', function (Blueprint $table): void {
            $table->dropColumn(['processing_lease_until', 'attempt_count']);
        });

        Schema::table('tm_outbox_events', function (Blueprint $table): void {
            $table->dropColumn([
                'lease_token', 'leased_until', 'next_attempt_at', 'last_error_code',
                'last_error_message', 'external_delivery_id', 'failed_at',
            ]);
        });

        Schema::table('tm_scheduled_actions', function (Blueprint $table): void {
            $table->dropColumn(['lease_token', 'leased_until', 'last_error_code', 'last_error_message', 'result_payload']);
        });
    }
};

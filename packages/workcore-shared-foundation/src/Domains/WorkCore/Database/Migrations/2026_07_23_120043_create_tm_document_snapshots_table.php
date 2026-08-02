<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tm_document_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64);
            $table->string('document_type', 64);
            $table->string('document_id', 64);
            $table->unsignedInteger('version');
            $table->json('payload');
            $table->char('payload_hash', 64);
            $table->string('created_by_user_id', 64)->nullable();
            $table->string('created_by_actor_type', 32);
            $table->string('created_by_actor_id', 128);
            $table->string('updated_by_actor_type', 32);
            $table->string('updated_by_actor_id', 128);
            $table->string('correlation_id', 128)->nullable();
            $table->string('origin', 32)->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->unique(
                ['company_id', 'document_type', 'document_id', 'version'],
                'tm_document_snapshots_document_version_unique',
            );
            $table->index(['company_id', 'payload_hash'], 'tm_document_snapshots_company_hash_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_document_snapshots');
    }
};

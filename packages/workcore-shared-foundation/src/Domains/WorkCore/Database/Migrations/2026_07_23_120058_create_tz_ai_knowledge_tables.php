<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_ai_knowledge_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 64);
            $table->unsignedBigInteger('company_id');
            $table->string('source_type', 80)->default('manual');
            $table->string('source_public_id', 191)->nullable();
            $table->string('title', 255);
            $table->string('visibility', 80)->default('company');
            $table->string('required_permission', 160)->nullable();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->longText('content');
            $table->char('content_hash', 64);
            $table->unsignedInteger('content_length');
            $table->json('metadata_json')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'public_id'], 'ai_kdoc_company_public_uq');
            $table->index(['company_id', 'source_type', 'source_public_id'], 'ai_kdoc_company_source_idx');
            $table->index(['company_id', 'visibility', 'deleted_at'], 'ai_kdoc_company_visibility_idx');
            $table->index(['company_id', 'expires_at', 'deleted_at'], 'ai_kdoc_company_retention_idx');
        });

        Schema::create('tz_ai_knowledge_chunks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id');
            $table->unsignedBigInteger('company_id');
            $table->string('document_public_id', 64);
            $table->unsignedInteger('sequence');
            $table->string('visibility', 80)->default('company');
            $table->string('required_permission', 160)->nullable();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->text('content');
            $table->char('content_hash', 64);
            $table->unsignedInteger('token_estimate')->default(0);
            $table->unsignedInteger('start_offset')->default(0);
            $table->unsignedInteger('end_offset')->default(0);
            $table->json('metadata_json')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'public_id'], 'ai_kchunk_company_public_uq');
            $table->unique(['company_id', 'document_public_id', 'sequence'], 'ai_kchunk_doc_sequence_uq');
            $table->index(['company_id', 'visibility', 'expires_at'], 'ai_kchunk_access_expiry_idx');
            $table->index(['company_id', 'required_permission'], 'ai_kchunk_permission_idx');
            $table->index(['company_id', 'owner_user_id'], 'ai_kchunk_owner_idx');
            $table->fullText('content', 'ai_kchunk_content_ft');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_ai_knowledge_chunks');
        Schema::dropIfExists('tz_ai_knowledge_documents');
    }
};

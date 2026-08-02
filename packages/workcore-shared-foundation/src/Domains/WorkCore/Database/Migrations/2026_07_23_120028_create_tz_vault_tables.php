<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_vault_documents', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('classification', 40)->default('general');
            $table->string('status', 30)->default('draft');
            $table->string('owner_type', 80)->nullable();
            $table->char('owner_public_id', 26)->nullable();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('tz_customers')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->unsignedInteger('current_version')->default(1);
            $table->string('retention_category', 60)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status', 'classification']);
            $table->index(['company_id', 'owner_type', 'owner_public_id']);
            $table->index(['company_id', 'premises_id']);
            $table->index(['company_id', 'expires_at']);
        });

        Schema::create('tz_vault_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('tz_vault_documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->longText('content_ciphertext')->nullable();
            $table->string('storage_path', 500)->nullable();
            $table->string('original_name');
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('checksum_sha256', 64);
            $table->string('encryption_algorithm', 50)->default('laravel-encrypter');
            $table->unsignedSmallInteger('encryption_version')->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['document_id', 'version_number']);
            $table->index(['company_id', 'document_id', 'created_at'], 'tz_vault_versions_lookup_idx');
            $table->index(['company_id', 'checksum_sha256']);
        });

        Schema::create('tz_vault_document_comments', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('tz_vault_documents')->cascadeOnDelete();
            $table->foreignId('parent_comment_id')->nullable()->constrained('tz_vault_document_comments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('position')->nullable();
            $table->text('content');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'document_id', 'created_at'], 'tz_vault_comments_lookup_idx');
        });

        Schema::create('tz_vault_approvals', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('tz_vault_documents')->cascadeOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained('tz_vault_document_versions')->nullOnDelete();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_name')->nullable();
            $table->string('approver_email')->nullable();
            $table->string('action', 30);
            $table->text('revision_notes')->nullable();
            $table->char('signature_hash', 64)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('decided_at');
            $table->timestamp('created_at')->nullable();
            $table->index(['company_id', 'document_id', 'decided_at']);
        });

        Schema::create('tz_vault_access_links', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('tz_vault_documents')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('password_hash')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_views')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamp('last_viewed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'document_id', 'status']);
        });

        Schema::create('tz_vault_activity_log', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('tz_vault_documents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('access_link_id')->nullable()->constrained('tz_vault_access_links')->nullOnDelete();
            $table->string('action', 50);
            $table->json('metadata')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['company_id', 'document_id', 'created_at']);
            $table->index(['company_id', 'action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_vault_activity_log');
        Schema::dropIfExists('tz_vault_access_links');
        Schema::dropIfExists('tz_vault_approvals');
        Schema::dropIfExists('tz_vault_document_comments');
        Schema::dropIfExists('tz_vault_document_versions');
        Schema::dropIfExists('tz_vault_documents');
    }
};

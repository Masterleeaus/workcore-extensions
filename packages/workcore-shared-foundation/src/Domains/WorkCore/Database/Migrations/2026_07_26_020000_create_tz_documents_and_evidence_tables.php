<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_documents', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('document_number', 100)->nullable();
            $table->string('document_type', 80);
            $table->string('title', 240);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('visibility', 30)->default('company');
            $table->unsignedInteger('current_version_number')->default(0);
            $table->string('retention_class', 80)->nullable();
            $table->date('retention_until')->nullable();
            $table->boolean('legal_hold')->default(false);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'document_number'], 'tz_documents_company_number_uq');
            $table->index(['company_id', 'document_type', 'status'], 'tz_documents_type_status_idx');
            $table->index(['company_id', 'retention_until', 'legal_hold'], 'tz_documents_retention_idx');
        });

        Schema::create('tz_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('tz_documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_disk', 80)->default('private');
            $table->string('storage_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 160)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('checksum_sha256', 64);
            $table->text('change_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['company_id', 'document_id', 'version_number'], 'tz_document_versions_number_uq');
            $table->index(['company_id', 'checksum_sha256'], 'tz_document_versions_checksum_idx');
        });

        Schema::create('tz_document_links', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('tz_documents')->cascadeOnDelete();
            $table->string('subject_type', 80);
            $table->char('subject_public_id', 26);
            $table->string('relation_type', 60)->default('attachment');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['company_id', 'document_id', 'subject_type', 'subject_public_id', 'relation_type'], 'tz_document_links_subject_uq');
            $table->index(['company_id', 'subject_type', 'subject_public_id'], 'tz_document_links_subject_idx');
        });

        Schema::create('tz_document_comments', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('tz_documents')->cascadeOnDelete();
            $table->foreignId('parent_comment_id')->nullable()->constrained('tz_document_comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 30)->default('active');
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'document_id', 'status'], 'tz_document_comments_status_idx');
        });

        Schema::create('tz_document_approvals', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('tz_documents')->cascadeOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained('tz_document_versions')->nullOnDelete();
            $table->string('approval_type', 60)->default('document');
            $table->string('status', 30)->default('pending');
            $table->text('purpose')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'approver_user_id', 'status'], 'tz_document_approvals_queue_idx');
            $table->index(['company_id', 'document_id', 'status'], 'tz_document_approvals_document_idx');
        });

        Schema::create('tz_evidence_items', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('tz_documents')->nullOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained('tz_document_versions')->nullOnDelete();
            $table->string('source_type', 80)->nullable();
            $table->char('source_public_id', 26)->nullable();
            $table->string('subject_type', 80);
            $table->char('subject_public_id', 26);
            $table->string('evidence_type', 80);
            $table->string('phase', 30)->default('unspecified');
            $table->string('status', 30)->default('active');
            $table->string('title', 240)->nullable();
            $table->text('description')->nullable();
            $table->string('storage_disk', 80)->nullable();
            $table->string('storage_path', 500)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 160)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('checksum_sha256', 64);
            $table->timestamp('captured_at')->nullable();
            $table->foreignId('captured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'subject_type', 'subject_public_id'], 'tz_evidence_subject_idx');
            $table->index(['company_id', 'source_type', 'source_public_id'], 'tz_evidence_source_idx');
            $table->index(['company_id', 'checksum_sha256'], 'tz_evidence_checksum_idx');
        });

        Schema::create('tz_evidence_signoffs', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('evidence_item_id')->constrained('tz_evidence_items')->cascadeOnDelete();
            $table->string('signoff_type', 60)->default('verification');
            $table->string('outcome', 30);
            $table->string('signer_role', 100)->nullable();
            $table->text('statement')->nullable();
            $table->char('signed_checksum_sha256', 64);
            $table->foreignId('signer_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('signed_at')->useCurrent();
            $table->json('signature_metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'evidence_item_id', 'signoff_type'], 'tz_evidence_signoffs_item_idx');
            $table->index(['company_id', 'signer_user_id', 'signed_at'], 'tz_evidence_signoffs_signer_idx');
        });

        Schema::create('tz_evidence_chain_events', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('evidence_item_id')->constrained('tz_evidence_items')->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->char('previous_event_hash', 64)->nullable();
            $table->char('payload_hash', 64);
            $table->char('event_hash', 64);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->useCurrent();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['company_id', 'event_hash'], 'tz_evidence_chain_event_hash_uq');
            $table->index(['company_id', 'evidence_item_id', 'occurred_at'], 'tz_evidence_chain_item_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_evidence_chain_events');
        Schema::dropIfExists('tz_evidence_signoffs');
        Schema::dropIfExists('tz_evidence_items');
        Schema::dropIfExists('tz_document_approvals');
        Schema::dropIfExists('tz_document_comments');
        Schema::dropIfExists('tz_document_links');
        Schema::dropIfExists('tz_document_versions');
        Schema::dropIfExists('tz_documents');
    }
};

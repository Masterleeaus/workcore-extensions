<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pm_compliance_rule_packs')) {
            Schema::create('pm_compliance_rule_packs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('key', 120);
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('profile_key', 80)->nullable()->index();
                $table->string('jurisdiction_country', 8)->nullable()->index();
                $table->string('jurisdiction_region', 80)->nullable()->index();
                $table->string('jurisdiction_locality', 120)->nullable();
                $table->string('version', 40)->default('1.0.0');
                $table->string('status', 30)->default('draft')->index();
                $table->string('source_title')->nullable();
                $table->text('source_url')->nullable();
                $table->date('source_published_at')->nullable();
                $table->string('verification_status', 30)->default('unverified')->index();
                $table->timestamp('verified_at')->nullable();
                $table->unsignedBigInteger('verified_by_user_id')->nullable()->index();
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->boolean('requires_local_verification')->default(true);
                $table->text('disclaimer')->nullable();
                $table->json('template_requirements');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'key', 'version'], 'pm_compliance_pack_company_key_version_unique');
            });
        }

        if (!Schema::hasTable('pm_compliance_requirements')) {
            Schema::create('pm_compliance_requirements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->nullable()->index();
                $table->unsignedBigInteger('responsible_party_role_id')->nullable()->index();
                $table->unsignedBigInteger('rule_pack_id')->nullable()->index();
                $table->string('pack_requirement_key', 120)->nullable();
                $table->string('requirement_type', 80)->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('jurisdiction_country', 8)->nullable();
                $table->string('jurisdiction_region', 80)->nullable();
                $table->string('jurisdiction_locality', 120)->nullable();
                $table->string('authority_name')->nullable();
                $table->string('source_title')->nullable();
                $table->text('source_url')->nullable();
                $table->string('legal_reference')->nullable();
                $table->string('source_verification_status', 30)->default('unverified')->index();
                $table->timestamp('source_verified_at')->nullable();
                $table->unsignedBigInteger('source_verified_by_user_id')->nullable()->index();
                $table->string('recurrence_type', 30)->default('none');
                $table->unsignedInteger('recurrence_interval')->nullable();
                $table->unsignedTinyInteger('annual_month')->nullable();
                $table->unsignedTinyInteger('annual_day')->nullable();
                $table->date('starts_on')->nullable();
                $table->timestamp('initial_due_at')->nullable();
                $table->timestamp('next_due_at')->nullable()->index();
                $table->timestamp('last_completed_at')->nullable();
                $table->unsignedInteger('warning_days')->default(30);
                $table->unsignedInteger('grace_days')->default(0);
                $table->string('severity', 20)->default('medium')->index();
                $table->string('status', 30)->default('active')->index();
                $table->boolean('evidence_required')->default(true);
                $table->json('evidence_types')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'premise_id', 'rule_pack_id', 'pack_requirement_key'], 'pm_compliance_pack_requirement_unique');
                $table->index(['company_id', 'premise_id', 'status', 'next_due_at'], 'pm_compliance_requirement_due_idx');
            });
        }

        if (!Schema::hasTable('pm_compliance_occurrences')) {
            Schema::create('pm_compliance_occurrences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('requirement_id')->index();
                $table->unsignedInteger('sequence')->default(1);
                $table->timestamp('due_at')->index();
                $table->timestamp('warning_at')->nullable()->index();
                $table->string('status', 30)->default('pending')->index();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedBigInteger('completed_by_user_id')->nullable()->index();
                $table->timestamp('waived_at')->nullable();
                $table->unsignedBigInteger('waived_by_user_id')->nullable()->index();
                $table->text('waiver_reason')->nullable();
                $table->unsignedBigInteger('evidence_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['requirement_id', 'sequence'], 'pm_compliance_occurrence_sequence_unique');
                $table->index(['company_id', 'premise_id', 'status', 'due_at'], 'pm_compliance_occurrence_due_idx');
            });
        }

        if (!Schema::hasTable('pm_compliance_evidence')) {
            Schema::create('pm_compliance_evidence', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('requirement_id')->index();
                $table->unsignedBigInteger('occurrence_id')->nullable()->index();
                $table->unsignedBigInteger('document_id')->nullable()->index();
                $table->string('evidence_type', 80)->index();
                $table->string('title');
                $table->string('reference')->nullable();
                $table->date('issued_on')->nullable();
                $table->date('expires_on')->nullable()->index();
                $table->string('status', 30)->default('submitted')->index();
                $table->timestamp('verified_at')->nullable();
                $table->unsignedBigInteger('verified_by_user_id')->nullable()->index();
                $table->text('rejection_reason')->nullable();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'premise_id', 'requirement_id'], 'pm_compliance_evidence_requirement_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_compliance_evidence');
        Schema::dropIfExists('pm_compliance_occurrences');
        Schema::dropIfExists('pm_compliance_requirements');
        Schema::dropIfExists('pm_compliance_rule_packs');
    }
};

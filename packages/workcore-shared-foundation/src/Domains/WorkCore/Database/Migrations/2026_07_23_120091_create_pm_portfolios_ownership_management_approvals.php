<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pm_premise_portfolios')) {
            Schema::create('pm_premise_portfolios', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('parent_portfolio_id')->nullable()->index();
                $table->string('code', 80)->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status', 30)->default('active')->index();
                $table->string('external_reference')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->foreign('parent_portfolio_id')->references('id')->on('pm_premise_portfolios')->nullOnDelete();
                $table->unique(['company_id', 'code'], 'pm_portfolio_company_code_unique');
            });
        }

        if (!Schema::hasTable('pm_premise_portfolio_memberships')) {
            Schema::create('pm_premise_portfolio_memberships', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('portfolio_id')->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->string('membership_type', 30)->default('primary')->index();
                $table->date('valid_from');
                $table->date('valid_until')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->foreign('portfolio_id')->references('id')->on('pm_premise_portfolios')->cascadeOnDelete();
                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->index(['company_id', 'premise_id', 'membership_type', 'valid_until'], 'pm_portfolio_membership_active_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_ownership_interests')) {
            Schema::create('pm_premise_ownership_interests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('party_role_id')->index();
                $table->unsignedBigInteger('document_id')->nullable()->index();
                $table->string('ownership_type', 40)->default('sole')->index();
                $table->decimal('share_percent', 7, 4)->nullable();
                $table->boolean('is_primary')->default(false)->index();
                $table->string('title_reference')->nullable();
                $table->string('external_reference')->nullable();
                $table->date('valid_from');
                $table->date('valid_until')->nullable()->index();
                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('party_role_id')->references('id')->on('pm_premise_party_roles')->restrictOnDelete();
                $table->foreign('document_id')->references('id')->on('pm_property_documents')->nullOnDelete();
                $table->index(['company_id', 'premise_id', 'valid_until'], 'pm_ownership_active_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_management_authorities')) {
            Schema::create('pm_premise_management_authorities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('party_role_id')->index();
                $table->unsignedBigInteger('agreement_id')->nullable()->index();
                $table->unsignedBigInteger('document_id')->nullable()->index();
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->index();
                $table->string('authority_type', 50)->index();
                $table->string('status', 30)->default('draft')->index();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable()->index();
                $table->date('notice_given_at')->nullable();
                $table->date('ended_at')->nullable();
                $table->unsignedInteger('notice_period_days')->nullable();
                $table->json('authorised_actions')->nullable();
                $table->json('restrictions')->nullable();
                $table->string('titan_money_policy_reference')->nullable();
                $table->string('external_reference')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('party_role_id')->references('id')->on('pm_premise_party_roles')->restrictOnDelete();
                $table->foreign('agreement_id')->references('id')->on('pm_premise_agreements')->nullOnDelete();
                $table->foreign('document_id')->references('id')->on('pm_property_documents')->nullOnDelete();
                $table->index(['company_id', 'premise_id', 'status'], 'pm_management_authority_status_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_approval_requests')) {
            Schema::create('pm_premise_approval_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('portfolio_id')->nullable()->index();
                $table->unsignedBigInteger('requested_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('requested_party_role_id')->nullable()->index();
                $table->unsignedBigInteger('legacy_property_approval_id')->nullable()->unique();
                $table->string('request_type', 50)->default('other')->index();
                $table->string('linked_module')->nullable();
                $table->unsignedBigInteger('linked_id')->nullable();
                $table->string('title');
                $table->text('summary')->nullable();
                $table->string('status', 30)->default('draft')->index();
                $table->unsignedSmallInteger('required_decisions')->default(1);
                $table->json('payload_snapshot')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('due_at')->nullable()->index();
                $table->timestamp('decided_at')->nullable();
                $table->timestamp('implemented_at')->nullable();
                $table->timestamp('withdrawn_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('portfolio_id')->references('id')->on('pm_premise_portfolios')->nullOnDelete();
                $table->foreign('requested_party_role_id')->references('id')->on('pm_premise_party_roles')->nullOnDelete();
                $table->index(['company_id', 'premise_id', 'status'], 'pm_approval_request_status_idx');
                $table->index(['company_id', 'requested_party_role_id', 'status'], 'pm_approval_party_status_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_approval_decisions')) {
            Schema::create('pm_premise_approval_decisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('approval_request_id')->index();
                $table->unsignedBigInteger('party_role_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('decision', 20)->index();
                $table->text('note')->nullable();
                $table->timestamp('decided_at');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->foreign('approval_request_id')->references('id')->on('pm_premise_approval_requests')->cascadeOnDelete();
                $table->foreign('party_role_id')->references('id')->on('pm_premise_party_roles')->nullOnDelete();
            });
        }

        if (Schema::hasTable('pm_premise_portal_grants') && !Schema::hasColumn('pm_premise_portal_grants', 'portfolio_id')) {
            Schema::table('pm_premise_portal_grants', function (Blueprint $table) {
                $table->unsignedBigInteger('portfolio_id')->nullable()->after('premise_id')->index();
                $table->foreign('portfolio_id')->references('id')->on('pm_premise_portfolios')->nullOnDelete();
            });
        }

        $this->backfillLegacyApprovals();
    }

    private function backfillLegacyApprovals(): void
    {
        if (!Schema::hasTable('pm_property_approvals') || !Schema::hasTable('pm_premise_approval_requests')) return;

        DB::table('pm_property_approvals')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                if (DB::table('pm_premise_approval_requests')->where('legacy_property_approval_id', $row->id)->exists()) continue;
                $status = in_array($row->status, ['pending', 'approved', 'rejected'], true) ? $row->status : 'pending';
                DB::table('pm_premise_approval_requests')->insert([
                    'company_id' => $row->company_id,
                    'premise_id' => $row->property_id,
                    'requested_by_user_id' => $row->requested_by,
                    'legacy_property_approval_id' => $row->id,
                    'request_type' => 'other',
                    'title' => $row->subject,
                    'status' => $status,
                    'required_decisions' => 1,
                    'payload_snapshot' => $row->request_payload,
                    'requested_at' => $row->requested_at,
                    'decided_at' => $row->decided_at,
                    'metadata' => json_encode(['source' => 'legacy_property_approval']),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_premise_portal_grants') && Schema::hasColumn('pm_premise_portal_grants', 'portfolio_id')) {
            Schema::table('pm_premise_portal_grants', function (Blueprint $table) {
                $table->dropForeign(['portfolio_id']);
                $table->dropColumn('portfolio_id');
            });
        }
        Schema::dropIfExists('pm_premise_approval_decisions');
        Schema::dropIfExists('pm_premise_approval_requests');
        Schema::dropIfExists('pm_premise_management_authorities');
        Schema::dropIfExists('pm_premise_ownership_interests');
        Schema::dropIfExists('pm_premise_portfolio_memberships');
        Schema::dropIfExists('pm_premise_portfolios');
    }
};

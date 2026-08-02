<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('tz_ndis_participants')) {
            Schema::create('tz_ndis_participants', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26);
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('premise_party_role_id')->nullable()->index();
                $table->unsignedBigInteger('occupancy_id')->nullable()->index();
                $table->unsignedBigInteger('portal_user_id')->nullable()->index();
                $table->string('participant_reference', 100)->nullable()->index();
                $table->text('ndis_number_encrypted')->nullable();
                $table->char('ndis_number_hash', 64)->nullable();
                $table->string('ndis_number_last4', 4)->nullable();
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->string('preferred_name', 100)->nullable();
                $table->string('display_name', 191);
                $table->date('date_of_birth')->nullable()->index();
                $table->string('pronouns', 80)->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 80)->nullable();
                $table->string('preferred_communication', 40)->default('phone');
                $table->string('preferred_language', 80)->nullable();
                $table->boolean('interpreter_required')->default(false);
                $table->string('status', 32)->default('prospective')->index();
                $table->string('funding_management', 32)->default('unknown')->index();
                $table->string('consent_status', 32)->default('pending')->index();
                $table->date('consent_review_on')->nullable()->index();
                $table->string('risk_level', 32)->default('standard')->index();
                $table->boolean('restricted_access')->default(false)->index();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('created_by_user_id');
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('customer_id')->references('id')->on('tz_customers')->nullOnDelete();
                $table->foreign('premise_party_role_id')->references('id')->on('pm_premise_party_roles')->nullOnDelete();
                $table->foreign('occupancy_id')->references('id')->on('pm_premise_occupancies')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'tz_ndis_participant_public_uq');
                $table->unique(['company_id', 'ndis_number_hash'], 'tz_ndis_participant_number_uq');
                $table->index(['company_id', 'status', 'display_name'], 'tz_ndis_participant_status_idx');
            });
        }

        if (! Schema::hasTable('tz_ndis_participant_contacts')) {
            Schema::create('tz_ndis_participant_contacts', function (Blueprint $table): void {
                $table->id(); $table->char('public_id', 26); $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('participant_id')->index();
                $table->unsignedBigInteger('customer_contact_id')->nullable()->index();
                $table->string('contact_type', 50)->index();
                $table->string('display_name', 191); $table->string('organisation_name')->nullable();
                $table->string('relationship', 100)->nullable(); $table->string('email')->nullable(); $table->string('phone', 80)->nullable();
                $table->json('authority_scope')->nullable(); $table->boolean('is_primary')->default(false)->index();
                $table->boolean('may_receive_information')->default(false); $table->boolean('may_make_decisions')->default(false);
                $table->date('valid_from')->nullable(); $table->date('valid_until')->nullable()->index();
                $table->string('consent_status', 32)->default('pending')->index(); $table->json('metadata')->nullable();
                $table->unsignedBigInteger('created_by_user_id'); $table->timestamps(); $table->softDeletes();
                $table->foreign('participant_id')->references('id')->on('tz_ndis_participants')->cascadeOnDelete();
                $table->foreign('customer_contact_id')->references('id')->on('tz_customer_contacts')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'tz_ndis_contact_public_uq');
                $table->index(['company_id', 'participant_id', 'contact_type'], 'tz_ndis_contact_type_idx');
            });
        }

        if (! Schema::hasTable('tz_ndis_plans')) {
            Schema::create('tz_ndis_plans', function (Blueprint $table): void {
                $table->id(); $table->char('public_id', 26); $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('participant_id')->index(); $table->unsignedBigInteger('plan_manager_contact_id')->nullable()->index();
                $table->string('plan_number', 100); $table->string('status', 32)->default('draft')->index();
                $table->date('starts_on')->index(); $table->date('ends_on')->index(); $table->date('review_on')->nullable()->index();
                $table->string('funding_management', 32)->default('unknown')->index(); $table->char('currency', 3)->default('AUD');
                $table->decimal('total_budget', 16, 4)->default(0); $table->text('notes')->nullable(); $table->json('metadata')->nullable();
                $table->unsignedBigInteger('created_by_user_id'); $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->timestamps(); $table->softDeletes();
                $table->foreign('participant_id')->references('id')->on('tz_ndis_participants')->cascadeOnDelete();
                $table->foreign('plan_manager_contact_id')->references('id')->on('tz_ndis_participant_contacts')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'tz_ndis_plan_public_uq');
                $table->unique(['company_id', 'participant_id', 'plan_number'], 'tz_ndis_plan_number_uq');
                $table->index(['company_id', 'participant_id', 'status', 'ends_on'], 'tz_ndis_plan_status_idx');
            });
        }

        if (! Schema::hasTable('tz_ndis_plan_budgets')) {
            Schema::create('tz_ndis_plan_budgets', function (Blueprint $table): void {
                $table->id(); $table->char('public_id', 26); $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('plan_id')->index(); $table->string('category_code', 80); $table->string('category_name', 191);
                $table->string('support_purpose', 32)->default('core')->index(); $table->decimal('allocated_amount', 16, 4)->default(0);
                $table->decimal('committed_amount', 16, 4)->default(0); $table->decimal('spent_amount', 16, 4)->default(0);
                $table->boolean('flexible')->default(false); $table->json('metadata')->nullable(); $table->timestamps();
                $table->foreign('plan_id')->references('id')->on('tz_ndis_plans')->cascadeOnDelete();
                $table->unique(['company_id', 'public_id'], 'tz_ndis_budget_public_uq');
                $table->unique(['plan_id', 'category_code'], 'tz_ndis_budget_category_uq');
            });
        }

        if (! Schema::hasTable('tz_ndis_participant_goals')) {
            Schema::create('tz_ndis_participant_goals', function (Blueprint $table): void {
                $table->id(); $table->char('public_id', 26); $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('participant_id')->index(); $table->unsignedBigInteger('plan_id')->nullable()->index();
                $table->string('status', 32)->default('active')->index(); $table->string('title', 191); $table->text('description')->nullable();
                $table->date('target_on')->nullable()->index(); $table->date('achieved_on')->nullable(); $table->text('outcome')->nullable();
                $table->string('visibility', 32)->default('team')->index(); $table->unsignedInteger('sort_order')->default(0);
                $table->json('metadata')->nullable(); $table->unsignedBigInteger('created_by_user_id'); $table->timestamps(); $table->softDeletes();
                $table->foreign('participant_id')->references('id')->on('tz_ndis_participants')->cascadeOnDelete();
                $table->foreign('plan_id')->references('id')->on('tz_ndis_plans')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'tz_ndis_goal_public_uq');
                $table->index(['company_id', 'participant_id', 'status'], 'tz_ndis_goal_status_idx');
            });
        }

        if (! Schema::hasTable('tz_ndis_service_agreements')) {
            Schema::create('tz_ndis_service_agreements', function (Blueprint $table): void {
                $table->id(); $table->char('public_id', 26); $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('participant_id')->index(); $table->unsignedBigInteger('plan_id')->nullable()->index();
                $table->string('agreement_number', 100); $table->string('status', 32)->default('draft')->index();
                $table->date('starts_on')->index(); $table->date('ends_on')->nullable()->index(); $table->timestamp('signed_at')->nullable();
                $table->string('funding_management', 32)->default('unknown')->index(); $table->char('currency', 3)->default('AUD');
                $table->json('cancellation_terms')->nullable(); $table->json('travel_terms')->nullable(); $table->text('notes')->nullable();
                $table->json('metadata')->nullable(); $table->unsignedBigInteger('created_by_user_id'); $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->timestamps(); $table->softDeletes();
                $table->foreign('participant_id')->references('id')->on('tz_ndis_participants')->cascadeOnDelete();
                $table->foreign('plan_id')->references('id')->on('tz_ndis_plans')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'tz_ndis_agreement_public_uq');
                $table->unique(['company_id', 'participant_id', 'agreement_number'], 'tz_ndis_agreement_number_uq');
            });
        }

        if (! Schema::hasTable('tz_ndis_service_agreement_items')) {
            Schema::create('tz_ndis_service_agreement_items', function (Blueprint $table): void {
                $table->id(); $table->char('public_id', 26); $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('agreement_id')->index(); $table->unsignedBigInteger('plan_budget_id')->nullable()->index();
                $table->string('category_code', 80)->index(); $table->string('support_item_code', 100)->index();
                $table->string('support_item_name', 191); $table->string('unit', 32)->default('hour')->index();
                $table->decimal('unit_rate', 14, 4)->default(0); $table->decimal('maximum_units', 14, 4)->nullable();
                $table->decimal('maximum_amount', 16, 4)->nullable(); $table->string('claim_type', 40)->default('standard')->index();
                $table->string('gst_code', 20)->default('GST_FREE'); $table->date('active_from')->nullable(); $table->date('active_until')->nullable()->index();
                $table->string('status', 32)->default('active')->index(); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
                $table->foreign('agreement_id')->references('id')->on('tz_ndis_service_agreements')->cascadeOnDelete();
                $table->foreign('plan_budget_id')->references('id')->on('tz_ndis_plan_budgets')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'tz_ndis_agreement_item_public_uq');
                $table->unique(['agreement_id', 'support_item_code', 'active_from'], 'tz_ndis_agreement_item_code_uq');
            });
        }

        if (! Schema::hasTable('tz_ndis_support_deliveries')) {
            Schema::create('tz_ndis_support_deliveries', function (Blueprint $table): void {
                $table->id(); $table->char('public_id', 26); $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('participant_id')->index(); $table->unsignedBigInteger('plan_id')->nullable()->index();
                $table->unsignedBigInteger('agreement_id')->index(); $table->unsignedBigInteger('agreement_item_id')->index();
                $table->unsignedBigInteger('goal_id')->nullable()->index(); $table->unsignedBigInteger('worker_id')->nullable()->index();
                $table->unsignedBigInteger('appointment_id')->nullable()->index(); $table->unsignedBigInteger('work_order_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->nullable()->index(); $table->unsignedBigInteger('occupancy_id')->nullable()->index();
                $table->date('service_date')->index(); $table->dateTime('started_at')->nullable(); $table->dateTime('ended_at')->nullable();
                $table->unsignedInteger('scheduled_minutes')->default(0); $table->unsignedInteger('delivered_minutes')->default(0);
                $table->string('outcome', 40)->default('delivered')->index(); $table->string('unit', 32)->default('hour');
                $table->decimal('quantity', 14, 4)->default(0); $table->decimal('unit_rate_snapshot', 14, 4)->default(0);
                $table->decimal('claimable_amount', 16, 4)->default(0); $table->unsignedInteger('travel_minutes')->default(0);
                $table->decimal('travel_kilometres', 12, 4)->default(0); $table->decimal('travel_amount', 14, 4)->default(0);
                $table->string('status', 32)->default('recorded')->index(); $table->text('cancellation_reason')->nullable();
                $table->text('delivery_notes')->nullable(); $table->json('evidence')->nullable(); $table->json('metadata')->nullable();
                $table->unsignedBigInteger('recorded_by_user_id'); $table->unsignedBigInteger('approved_by_user_id')->nullable();
                $table->timestamp('approved_at')->nullable(); $table->timestamps(); $table->softDeletes();
                $table->foreign('participant_id')->references('id')->on('tz_ndis_participants')->cascadeOnDelete();
                $table->foreign('plan_id')->references('id')->on('tz_ndis_plans')->nullOnDelete();
                $table->foreign('agreement_id')->references('id')->on('tz_ndis_service_agreements')->restrictOnDelete();
                $table->foreign('agreement_item_id')->references('id')->on('tz_ndis_service_agreement_items')->restrictOnDelete();
                $table->foreign('goal_id')->references('id')->on('tz_ndis_participant_goals')->nullOnDelete();
                $table->foreign('worker_id')->references('id')->on('tz_workers')->nullOnDelete();
                $table->foreign('appointment_id')->references('id')->on('tz_appointments')->nullOnDelete();
                $table->foreign('work_order_id')->references('id')->on('tz_work_orders')->nullOnDelete();
                $table->foreign('premise_id')->references('id')->on('pm_properties')->nullOnDelete();
                $table->foreign('occupancy_id')->references('id')->on('pm_premise_occupancies')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'tz_ndis_delivery_public_uq');
                $table->index(['company_id', 'participant_id', 'service_date'], 'tz_ndis_delivery_participant_idx');
                $table->index(['company_id', 'status', 'service_date'], 'tz_ndis_delivery_status_idx');
            });
        }

        if (! Schema::hasTable('tz_ndis_case_notes')) {
            Schema::create('tz_ndis_case_notes', function (Blueprint $table): void {
                $table->id(); $table->char('public_id', 26); $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('participant_id')->index(); $table->unsignedBigInteger('delivery_id')->nullable()->index();
                $table->unsignedBigInteger('goal_id')->nullable()->index(); $table->unsignedBigInteger('worker_id')->nullable()->index();
                $table->string('note_type', 50)->default('progress')->index(); $table->string('visibility', 32)->default('team')->index();
                $table->string('sensitivity', 32)->default('normal')->index(); $table->dateTime('occurred_at')->index();
                $table->string('summary', 500)->nullable(); $table->longText('body_encrypted'); $table->char('body_hash', 64);
                $table->timestamp('shared_with_participant_at')->nullable(); $table->json('metadata')->nullable();
                $table->unsignedBigInteger('author_user_id'); $table->timestamps(); $table->softDeletes();
                $table->foreign('participant_id')->references('id')->on('tz_ndis_participants')->cascadeOnDelete();
                $table->foreign('delivery_id')->references('id')->on('tz_ndis_support_deliveries')->nullOnDelete();
                $table->foreign('goal_id')->references('id')->on('tz_ndis_participant_goals')->nullOnDelete();
                $table->foreign('worker_id')->references('id')->on('tz_workers')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'tz_ndis_note_public_uq');
                $table->index(['company_id', 'participant_id', 'visibility', 'occurred_at'], 'tz_ndis_note_access_idx');
            });
        }

        if (! Schema::hasTable('tz_ndis_incident_links')) {
            Schema::create('tz_ndis_incident_links', function (Blueprint $table): void {
                $table->id(); $table->char('public_id', 26); $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('participant_id')->index(); $table->unsignedBigInteger('incident_id')->index();
                $table->unsignedBigInteger('delivery_id')->nullable()->index(); $table->string('relationship', 50)->default('affected_participant')->index();
                $table->string('participant_impact', 32)->default('none')->index(); $table->boolean('restrictive_practice')->default(false)->index();
                $table->boolean('reportable_to_ndis')->default(false)->index(); $table->timestamp('reported_to_ndis_at')->nullable();
                $table->text('follow_up')->nullable(); $table->json('metadata')->nullable(); $table->unsignedBigInteger('created_by_user_id');
                $table->timestamps();
                $table->foreign('participant_id')->references('id')->on('tz_ndis_participants')->cascadeOnDelete();
                $table->foreign('incident_id')->references('id')->on('tz_safety_incidents')->cascadeOnDelete();
                $table->foreign('delivery_id')->references('id')->on('tz_ndis_support_deliveries')->nullOnDelete();
                $table->unique(['company_id', 'public_id'], 'tz_ndis_incident_link_public_uq');
                $table->unique(['participant_id', 'incident_id'], 'tz_ndis_incident_participant_uq');
            });
        }

        if (! Schema::hasTable('tz_ndis_worker_matches')) {
            Schema::create('tz_ndis_worker_matches', function (Blueprint $table): void {
                $table->id(); $table->char('public_id', 26); $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('participant_id')->index(); $table->unsignedBigInteger('worker_id')->index();
                $table->string('support_class', 80)->nullable()->index(); $table->string('status', 32)->default('proposed')->index();
                $table->unsignedTinyInteger('compatibility_score')->nullable(); $table->json('matched_preferences')->nullable();
                $table->json('restrictions')->nullable(); $table->text('reason')->nullable(); $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable()->index(); $table->unsignedBigInteger('approved_by_user_id')->nullable();
                $table->timestamp('approved_at')->nullable(); $table->json('metadata')->nullable(); $table->timestamps(); $table->softDeletes();
                $table->foreign('participant_id')->references('id')->on('tz_ndis_participants')->cascadeOnDelete();
                $table->foreign('worker_id')->references('id')->on('tz_workers')->cascadeOnDelete();
                $table->unique(['company_id', 'public_id'], 'tz_ndis_worker_match_public_uq');
                $table->unique(['participant_id', 'worker_id', 'support_class'], 'tz_ndis_worker_match_uq');
            });
        }

        if (! Schema::hasTable('tz_ndis_claim_lines')) {
            Schema::create('tz_ndis_claim_lines', function (Blueprint $table): void {
                $table->id(); $table->char('public_id', 26); $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('participant_id')->index(); $table->unsignedBigInteger('plan_id')->nullable()->index();
                $table->unsignedBigInteger('plan_budget_id')->nullable()->index(); $table->unsignedBigInteger('agreement_id')->index();
                $table->unsignedBigInteger('agreement_item_id')->index(); $table->unsignedBigInteger('delivery_id')->index();
                $table->string('claim_reference', 100)->nullable()->index(); $table->string('external_batch_reference', 191)->nullable()->index();
                $table->string('external_claim_reference', 191)->nullable()->index(); $table->string('support_item_code', 100)->index();
                $table->string('claim_type', 40)->default('standard')->index(); $table->date('service_date')->index();
                $table->string('unit', 32); $table->decimal('quantity', 14, 4); $table->decimal('rate_snapshot', 14, 4);
                $table->decimal('amount', 16, 4); $table->string('gst_code', 20)->default('GST_FREE');
                $table->string('funding_management', 32)->default('unknown')->index(); $table->string('status', 32)->default('prepared')->index();
                $table->text('hold_reason')->nullable(); $table->text('rejection_reason')->nullable(); $table->timestamp('exported_at')->nullable();
                $table->timestamp('reconciled_at')->nullable(); $table->string('finance_provider', 80)->nullable();
                $table->string('finance_external_id', 191)->nullable()->index(); $table->json('metadata')->nullable();
                $table->unsignedBigInteger('prepared_by_user_id'); $table->timestamps(); $table->softDeletes();
                $table->foreign('participant_id')->references('id')->on('tz_ndis_participants')->cascadeOnDelete();
                $table->foreign('plan_id')->references('id')->on('tz_ndis_plans')->nullOnDelete();
                $table->foreign('plan_budget_id')->references('id')->on('tz_ndis_plan_budgets')->nullOnDelete();
                $table->foreign('agreement_id')->references('id')->on('tz_ndis_service_agreements')->restrictOnDelete();
                $table->foreign('agreement_item_id')->references('id')->on('tz_ndis_service_agreement_items')->restrictOnDelete();
                $table->foreign('delivery_id')->references('id')->on('tz_ndis_support_deliveries')->restrictOnDelete();
                $table->unique(['company_id', 'public_id'], 'tz_ndis_claim_public_uq');
                $table->unique(['company_id', 'delivery_id', 'claim_type'], 'tz_ndis_claim_delivery_uq');
                $table->index(['company_id', 'status', 'service_date'], 'tz_ndis_claim_queue_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_ndis_claim_lines');
        Schema::dropIfExists('tz_ndis_worker_matches');
        Schema::dropIfExists('tz_ndis_incident_links');
        Schema::dropIfExists('tz_ndis_case_notes');
        Schema::dropIfExists('tz_ndis_support_deliveries');
        Schema::dropIfExists('tz_ndis_service_agreement_items');
        Schema::dropIfExists('tz_ndis_service_agreements');
        Schema::dropIfExists('tz_ndis_participant_goals');
        Schema::dropIfExists('tz_ndis_plan_budgets');
        Schema::dropIfExists('tz_ndis_plans');
        Schema::dropIfExists('tz_ndis_participant_contacts');
        Schema::dropIfExists('tz_ndis_participants');
    }
};

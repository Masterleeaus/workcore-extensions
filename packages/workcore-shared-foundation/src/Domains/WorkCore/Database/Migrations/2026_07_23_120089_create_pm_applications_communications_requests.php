<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pm_premise_vacancy_enquiries')) {
            Schema::create('pm_premise_vacancy_enquiries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('vacancy_listing_id')->index();
                $table->unsignedBigInteger('space_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
                $table->unsignedBigInteger('converted_application_id')->nullable()->index();
                $table->string('public_reference', 50)->unique();
                $table->string('status', 30)->default('new')->index();
                $table->string('display_name');
                $table->string('email')->nullable();
                $table->string('phone', 80)->nullable();
                $table->string('preferred_contact', 30)->nullable();
                $table->text('message')->nullable();
                $table->string('source', 50)->default('public_vacancy');
                $table->timestamp('consent_at')->nullable();
                $table->timestamp('contacted_at')->nullable();
                $table->timestamp('qualified_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('vacancy_listing_id')->references('id')->on('pm_premise_vacancy_listings')->cascadeOnDelete();
                $table->foreign('space_id')->references('id')->on('pm_premise_spaces')->nullOnDelete();
                $table->index(['company_id', 'premise_id', 'status'], 'pm_enquiry_premise_status_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_applications')) {
            Schema::create('pm_premise_applications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->nullable()->index();
                $table->unsignedBigInteger('vacancy_listing_id')->nullable()->index();
                $table->unsignedBigInteger('enquiry_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
                $table->unsignedBigInteger('applicant_party_role_id')->nullable()->index();
                $table->unsignedBigInteger('converted_party_role_id')->nullable()->index();
                $table->unsignedBigInteger('converted_occupancy_id')->nullable()->index();
                $table->string('application_number', 60)->unique();
                $table->string('status', 40)->default('draft')->index();
                $table->string('applicant_name');
                $table->string('applicant_email')->nullable();
                $table->string('applicant_phone', 80)->nullable();
                $table->char('portal_token_hash', 64)->nullable()->unique();
                $table->string('portal_token_hint', 12)->nullable();
                $table->timestamp('portal_expires_at')->nullable();
                $table->timestamp('portal_last_accessed_at')->nullable();
                $table->json('applicant_payload')->nullable();
                $table->json('eligibility_answers')->nullable();
                $table->text('screening_notes')->nullable();
                $table->text('decision_reason')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('screened_at')->nullable();
                $table->timestamp('offered_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('withdrawn_at')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('space_id')->references('id')->on('pm_premise_spaces')->nullOnDelete();
                $table->foreign('vacancy_listing_id')->references('id')->on('pm_premise_vacancy_listings')->nullOnDelete();
                $table->foreign('enquiry_id')->references('id')->on('pm_premise_vacancy_enquiries')->nullOnDelete();
                $table->foreign('applicant_party_role_id')->references('id')->on('pm_premise_party_roles')->nullOnDelete();
                $table->foreign('converted_party_role_id')->references('id')->on('pm_premise_party_roles')->nullOnDelete();
                $table->foreign('converted_occupancy_id')->references('id')->on('pm_premise_occupancies')->nullOnDelete();
                $table->index(['company_id', 'premise_id', 'status'], 'pm_application_premise_status_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_application_events')) {
            Schema::create('pm_premise_application_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('application_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('actor_type', 30)->default('operator');
                $table->string('event_type', 50)->index();
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40)->nullable();
                $table->string('visibility', 30)->default('portal')->index();
                $table->text('summary');
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();
                $table->foreign('application_id')->references('id')->on('pm_premise_applications')->cascadeOnDelete();
                $table->index(['company_id', 'application_id', 'occurred_at'], 'pm_application_event_timeline_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_conversations')) {
            Schema::create('pm_premise_conversations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('portal_grant_id')->nullable()->index();
                $table->unsignedBigInteger('application_id')->nullable()->index();
                $table->unsignedBigInteger('party_role_id')->nullable()->index();
                $table->unsignedBigInteger('occupancy_id')->nullable()->index();
                $table->unsignedBigInteger('incident_id')->nullable()->index();
                $table->unsignedBigInteger('opened_by_user_id')->nullable()->index();
                $table->string('subject');
                $table->string('status', 30)->default('open')->index();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamp('closed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('portal_grant_id')->references('id')->on('pm_premise_portal_grants')->nullOnDelete();
                $table->foreign('application_id')->references('id')->on('pm_premise_applications')->nullOnDelete();
                $table->foreign('party_role_id')->references('id')->on('pm_premise_party_roles')->nullOnDelete();
                $table->foreign('occupancy_id')->references('id')->on('pm_premise_occupancies')->nullOnDelete();
                $table->foreign('incident_id')->references('id')->on('pm_premise_incidents')->nullOnDelete();
                $table->index(['company_id', 'premise_id', 'status'], 'pm_conversation_premise_status_idx');
                $table->unique(['company_id', 'application_id'], 'pm_conversation_application_unique');
                $table->unique(['company_id', 'portal_grant_id'], 'pm_conversation_portal_unique');
            });
        }

        if (!Schema::hasTable('pm_premise_messages')) {
            Schema::create('pm_premise_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('conversation_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('portal_grant_id')->nullable()->index();
                $table->unsignedBigInteger('application_id')->nullable()->index();
                $table->string('direction', 30)->index();
                $table->string('channel', 30)->default('portal')->index();
                $table->string('visibility', 30)->default('portal')->index();
                $table->text('body');
                $table->timestamp('sent_at');
                $table->timestamp('read_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->foreign('conversation_id')->references('id')->on('pm_premise_conversations')->cascadeOnDelete();
                $table->foreign('portal_grant_id')->references('id')->on('pm_premise_portal_grants')->nullOnDelete();
                $table->foreign('application_id')->references('id')->on('pm_premise_applications')->nullOnDelete();
                $table->index(['company_id', 'conversation_id', 'sent_at'], 'pm_message_timeline_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_notices')) {
            Schema::create('pm_premise_notices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('portal_grant_id')->nullable()->index();
                $table->unsignedBigInteger('party_role_id')->nullable()->index();
                $table->unsignedBigInteger('occupancy_id')->nullable()->index();
                $table->unsignedBigInteger('agreement_id')->nullable()->index();
                $table->unsignedBigInteger('drafted_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('issued_by_user_id')->nullable()->index();
                $table->string('notice_type', 60)->index();
                $table->string('title');
                $table->text('body');
                $table->string('status', 30)->default('draft')->index();
                $table->boolean('requires_acknowledgement')->default(false);
                $table->timestamp('approval_requested_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('withdrawn_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->text('withdrawal_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('portal_grant_id')->references('id')->on('pm_premise_portal_grants')->nullOnDelete();
                $table->foreign('party_role_id')->references('id')->on('pm_premise_party_roles')->nullOnDelete();
                $table->foreign('occupancy_id')->references('id')->on('pm_premise_occupancies')->nullOnDelete();
                $table->foreign('agreement_id')->references('id')->on('pm_premise_agreements')->nullOnDelete();
                $table->index(['company_id', 'premise_id', 'status'], 'pm_notice_premise_status_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_self_service_requests')) {
            Schema::create('pm_premise_self_service_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->nullable()->index();
                $table->unsignedBigInteger('portal_grant_id')->nullable()->index();
                $table->unsignedBigInteger('party_role_id')->nullable()->index();
                $table->unsignedBigInteger('occupancy_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
                $table->string('request_number', 60)->unique();
                $table->string('request_type', 60)->index();
                $table->string('priority', 20)->default('normal')->index();
                $table->string('privacy_level', 20)->default('standard')->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status', 30)->default('submitted')->index();
                $table->string('workcore_linked_module')->nullable();
                $table->string('workcore_linked_id')->nullable();
                $table->timestamp('workcore_linked_at')->nullable();
                $table->timestamp('submitted_at');
                $table->timestamp('triaged_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('space_id')->references('id')->on('pm_premise_spaces')->nullOnDelete();
                $table->foreign('portal_grant_id')->references('id')->on('pm_premise_portal_grants')->nullOnDelete();
                $table->foreign('party_role_id')->references('id')->on('pm_premise_party_roles')->nullOnDelete();
                $table->foreign('occupancy_id')->references('id')->on('pm_premise_occupancies')->nullOnDelete();
                $table->index(['company_id', 'premise_id', 'status'], 'pm_request_premise_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_premise_self_service_requests');
        Schema::dropIfExists('pm_premise_notices');
        Schema::dropIfExists('pm_premise_messages');
        Schema::dropIfExists('pm_premise_conversations');
        Schema::dropIfExists('pm_premise_application_events');
        Schema::dropIfExists('pm_premise_applications');
        Schema::dropIfExists('pm_premise_vacancy_enquiries');
    }
};

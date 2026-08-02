<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pm_premise_portal_grants')) {
            Schema::create('pm_premise_portal_grants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('party_role_id')->nullable()->index();
                $table->unsignedBigInteger('occupancy_id')->nullable()->index();
                $table->unsignedBigInteger('agreement_id')->nullable()->index();
                $table->unsignedBigInteger('granted_by_user_id')->nullable()->index();
                $table->string('label');
                $table->string('status', 30)->default('invited')->index();
                $table->char('token_hash', 64)->unique();
                $table->string('token_hint', 12);
                $table->json('capabilities');
                $table->timestamp('invited_at');
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('last_accessed_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('party_role_id')->references('id')->on('pm_premise_party_roles')->nullOnDelete();
                $table->foreign('occupancy_id')->references('id')->on('pm_premise_occupancies')->nullOnDelete();
                $table->foreign('agreement_id')->references('id')->on('pm_premise_agreements')->nullOnDelete();
                $table->index(['company_id', 'premise_id', 'status'], 'pm_portal_grant_premise_status_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_portal_events')) {
            Schema::create('pm_premise_portal_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('portal_grant_id')->index();
                $table->string('event_type', 60)->index();
                $table->timestamp('occurred_at');
                $table->string('ip_hash', 64)->nullable();
                $table->text('user_agent_summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->foreign('portal_grant_id')->references('id')->on('pm_premise_portal_grants')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('pm_premise_vacancy_listings')) {
            Schema::create('pm_premise_vacancy_listings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('premise_id')->index();
                $table->unsignedBigInteger('space_id')->index();
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->index();
                $table->string('public_slug', 120)->unique();
                $table->string('listing_type', 50)->default('space')->index();
                $table->string('status', 30)->default('draft')->index();
                $table->string('title');
                $table->text('summary')->nullable();
                $table->text('description')->nullable();
                $table->string('public_location')->nullable();
                $table->boolean('show_exact_address')->default(false);
                $table->date('available_from')->nullable()->index();
                $table->date('applications_close_at')->nullable()->index();
                $table->unsignedInteger('capacity')->nullable();
                $table->json('features')->nullable();
                $table->json('eligibility_notes')->nullable();
                $table->string('contact_mode', 30)->default('portal');
                $table->string('external_contact_reference')->nullable();
                $table->timestamp('approval_requested_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('paused_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('premise_id')->references('id')->on('pm_properties')->cascadeOnDelete();
                $table->foreign('space_id')->references('id')->on('pm_premise_spaces')->cascadeOnDelete();
                $table->index(['company_id', 'premise_id', 'status'], 'pm_vacancy_premise_status_idx');
                $table->index(['company_id', 'space_id', 'status'], 'pm_vacancy_space_status_idx');
            });
        }

        if (!Schema::hasTable('pm_premise_vacancy_publications')) {
            Schema::create('pm_premise_vacancy_publications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('vacancy_listing_id')->index();
                $table->unsignedBigInteger('requested_by_user_id')->nullable()->index();
                $table->string('channel', 50)->index();
                $table->string('status', 30)->default('pending')->index();
                $table->json('payload_snapshot');
                $table->string('external_reference')->nullable();
                $table->text('external_url')->nullable();
                $table->timestamp('requested_at');
                $table->timestamp('published_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('withdrawn_at')->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('vacancy_listing_id')->references('id')->on('pm_premise_vacancy_listings')->cascadeOnDelete();
                $table->index(['company_id', 'channel', 'status'], 'pm_vacancy_publication_channel_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_premise_vacancy_publications');
        Schema::dropIfExists('pm_premise_vacancy_listings');
        Schema::dropIfExists('pm_premise_portal_events');
        Schema::dropIfExists('pm_premise_portal_grants');
    }
};

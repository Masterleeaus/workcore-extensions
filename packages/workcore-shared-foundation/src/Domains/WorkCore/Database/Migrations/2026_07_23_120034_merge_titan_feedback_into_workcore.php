<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->createTaxonomyTables();
        $this->createFeedbackTables();
        $this->createSurveyTables();
        $this->createWorkflowTables();
        $this->createProjectionTables();
        $this->extendLegacyRebooking();
        $this->migrateLegacyReviews();
    }

    public function down(): void
    {
        if (Schema::hasTable('tz_rebooking_requests') && Schema::hasColumn('tz_rebooking_requests', 'feedback_item_id')) {
            Schema::table('tz_rebooking_requests', static function (Blueprint $table): void {
                $table->dropColumn('feedback_item_id');
            });
        }

        Schema::dropIfExists('tz_feedback_metric_snapshots');
        Schema::dropIfExists('tz_feedback_assignments');
        Schema::dropIfExists('tz_feedback_legacy_mappings');
        Schema::dropIfExists('tz_feedback_transitions');
        Schema::dropIfExists('tz_feedback_service_recoveries');
        Schema::dropIfExists('tz_feedback_moderation_actions');
        Schema::dropIfExists('tz_feedback_consents');
        Schema::dropIfExists('tz_feedback_survey_answers');
        Schema::dropIfExists('tz_feedback_survey_responses');
        Schema::dropIfExists('tz_feedback_tokens');
        Schema::dropIfExists('tz_feedback_requests');
        Schema::dropIfExists('tz_feedback_survey_questions');
        Schema::dropIfExists('tz_feedback_surveys');
        Schema::dropIfExists('tz_feedback_item_tag');
        Schema::dropIfExists('tz_feedback_insights');
        Schema::dropIfExists('tz_feedback_file_links');
        Schema::dropIfExists('tz_feedback_messages');
        Schema::dropIfExists('tz_feedback_items');
        Schema::dropIfExists('tz_feedback_tags');
        Schema::dropIfExists('tz_feedback_categories');
        Schema::dropIfExists('tz_feedback_channels');
    }

    private function createTaxonomyTables(): void
    {
        Schema::create('tz_feedback_channels', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name');
            $table->string('slug');
            $table->string('kind', 32)->default('web');
            $table->boolean('active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'slug'], 'tz_fb_channels_company_slug_uq');
        });

        Schema::create('tz_feedback_categories', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name');
            $table->string('slug');
            $table->json('applies_to')->nullable();
            $table->string('default_severity', 24)->nullable();
            $table->boolean('sensitive')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'slug'], 'tz_fb_categories_company_slug_uq');
        });

        Schema::create('tz_feedback_tags', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 32)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'slug'], 'tz_fb_tags_company_slug_uq');
        });
    }

    private function createFeedbackTables(): void
    {
        Schema::create('tz_feedback_items', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('feedback_request_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->unsignedBigInteger('premises_id')->nullable()->index();
            $table->unsignedBigInteger('work_order_id')->nullable()->index();
            $table->unsignedBigInteger('appointment_id')->nullable()->index();
            $table->unsignedBigInteger('worker_id')->nullable()->index();
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->unsignedBigInteger('requester_user_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
            $table->unsignedBigInteger('channel_id')->nullable()->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('kind', 32)->index();
            $table->string('visibility', 24)->default('private');
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedTinyInteger('nps_score')->nullable();
            $table->unsignedTinyInteger('csat_score')->nullable();
            $table->string('sentiment', 24)->default('neutral')->index();
            $table->string('status', 32)->default('open')->index();
            $table->string('priority', 24)->default('medium');
            $table->string('severity', 24)->nullable()->index();
            $table->string('moderation_status', 24)->default('not_required')->index();
            $table->string('publication_status', 24)->default('private')->index();
            $table->string('consent_status', 24)->default('unknown');
            $table->boolean('public_permission')->default(false);
            $table->boolean('requires_investigation')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamp('publication_approved_at')->nullable();
            $table->unsignedBigInteger('publication_approved_by_user_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('resolution_outcome')->nullable();
            $table->longText('resolution_summary')->nullable();
            $table->string('source_ref_type', 64)->nullable();
            $table->string('source_ref_id', 191)->nullable();
            $table->string('dedupe_key', 128)->nullable();
            $table->json('custom_meta')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['company_id', 'dedupe_key'], 'tz_fb_items_company_dedupe_uq');
            $table->index(['company_id', 'kind', 'status'], 'tz_fb_items_company_kind_status_ix');
            $table->index(['company_id', 'created_at'], 'tz_fb_items_company_created_ix');
        });

        Schema::create('tz_feedback_messages', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('feedback_item_id')->index();
            $table->unsignedBigInteger('author_user_id')->nullable()->index();
            $table->unsignedBigInteger('author_customer_id')->nullable()->index();
            $table->string('message_type', 32)->index();
            $table->longText('body');
            $table->longText('body_html')->nullable();
            $table->boolean('is_ai_generated')->default(false);
            $table->string('approval_status', 24)->default('not_required')->index();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('source_channel', 32)->default('internal');
            $table->string('delivery_status', 24)->default('not_requested')->index();
            $table->string('delivery_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['feedback_item_id', 'created_at'], 'tz_fb_messages_item_created_ix');
        });

        Schema::create('tz_feedback_file_links', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('feedback_item_id')->index();
            $table->unsignedBigInteger('message_id')->nullable()->index();
            $table->string('file_reference_type', 48)->default('titan_file');
            $table->string('file_reference_id', 128);
            $table->string('purpose', 48)->default('attachment');
            $table->string('visibility', 24)->default('private');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['feedback_item_id', 'file_reference_type', 'file_reference_id'], 'tz_fb_file_item_ref_uq');
        });

        Schema::create('tz_feedback_insights', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('feedback_item_id')->index();
            $table->string('analysis_type', 48)->index();
            $table->string('provider_route')->nullable();
            $table->string('status', 24)->default('complete');
            $table->string('label')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('generated_by_user_id')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['feedback_item_id', 'analysis_type'], 'tz_fb_insights_item_type_ix');
        });

        Schema::create('tz_feedback_item_tag', static function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('feedback_item_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();
            $table->primary(['feedback_item_id', 'tag_id'], 'tz_fb_item_tag_pk');
        });
    }

    private function createSurveyTables(): void
    {
        Schema::create('tz_feedback_surveys', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('type', 24)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedTinyInteger('scale_min')->nullable();
            $table->unsignedTinyInteger('scale_max')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tz_feedback_survey_questions', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('survey_id')->index();
            $table->string('key', 80);
            $table->string('type', 32);
            $table->text('prompt');
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['survey_id', 'key'], 'tz_fb_questions_survey_key_uq');
        });

        Schema::create('tz_feedback_requests', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('survey_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->unsignedBigInteger('premises_id')->nullable()->index();
            $table->unsignedBigInteger('work_order_id')->nullable()->index();
            $table->unsignedBigInteger('appointment_id')->nullable()->index();
            $table->unsignedBigInteger('worker_id')->nullable()->index();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('audience', 32)->default('customer');
            $table->string('channel', 32)->default('web');
            $table->string('source_ref_type', 64)->nullable();
            $table->string('source_ref_id', 191)->nullable();
            $table->string('dedupe_key', 128)->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'dedupe_key'], 'tz_fb_requests_company_dedupe_uq');
            $table->index(['company_id', 'source_ref_type', 'source_ref_id'], 'tz_fb_requests_source_ref_ix');
        });

        Schema::create('tz_feedback_tokens', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('feedback_request_id')->index();
            $table->char('token_hash', 64)->unique();
            $table->string('purpose', 32)->index();
            $table->unsignedSmallInteger('max_uses')->default(1);
            $table->unsignedSmallInteger('uses')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('tz_feedback_survey_responses', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('survey_id')->index();
            $table->unsignedBigInteger('feedback_request_id')->nullable()->index();
            $table->unsignedBigInteger('feedback_item_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('work_order_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('status', 24)->default('submitted');
            $table->timestamp('submitted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique('feedback_request_id', 'tz_fb_responses_request_uq');
        });

        Schema::create('tz_feedback_survey_answers', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('survey_response_id')->index();
            $table->unsignedBigInteger('survey_question_id')->index();
            $table->decimal('numeric_value', 12, 4)->nullable();
            $table->longText('text_value')->nullable();
            $table->json('json_value')->nullable();
            $table->timestamps();
            $table->unique(['survey_response_id', 'survey_question_id'], 'tz_fb_answers_response_question_uq');
        });

        Schema::create('tz_feedback_consents', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('purpose', 48);
            $table->string('status', 24);
            $table->string('source', 48)->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'subject_type', 'subject_id'], 'tz_fb_consents_subject_ix');
        });
    }

    private function createWorkflowTables(): void
    {
        Schema::create('tz_feedback_moderation_actions', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('feedback_item_id')->index();
            $table->unsignedBigInteger('message_id')->nullable()->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('action', 32)->index();
            $table->text('reason')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestamps();
        });

        Schema::create('tz_feedback_service_recoveries', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('feedback_item_id')->index();
            $table->unsignedBigInteger('support_ticket_id')->nullable()->index();
            $table->unsignedBigInteger('work_order_id')->nullable()->index();
            $table->unsignedBigInteger('owner_user_id')->nullable()->index();
            $table->string('action_type', 48);
            $table->string('status', 32)->default('proposed')->index();
            $table->string('severity', 24)->default('medium');
            $table->boolean('requires_approval')->default(false);
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('outcome')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('tz_feedback_transitions', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('feedback_item_id')->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['feedback_item_id', 'occurred_at'], 'tz_fb_transitions_item_time_ix');
        });

        Schema::create('tz_feedback_legacy_mappings', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('source_module', 64);
            $table->string('source_table', 64);
            $table->string('source_id', 128);
            $table->string('target_type', 64);
            $table->unsignedBigInteger('target_id');
            $table->char('source_checksum', 64)->nullable();
            $table->timestamp('imported_at');
            $table->json('metadata')->nullable();
            $table->unique(['company_id', 'source_module', 'source_table', 'source_id'], 'tz_fb_legacy_source_uq');
        });
    }

    private function createProjectionTables(): void
    {
        Schema::create('tz_feedback_assignments', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('feedback_item_id')->index();
            $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_team_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_by_user_id')->nullable()->index();
            $table->text('reason')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();
            $table->index(['feedback_item_id', 'unassigned_at'], 'tz_fb_assignments_item_active_ix');
        });

        Schema::create('tz_feedback_metric_snapshots', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('metric_key', 80)->index();
            $table->decimal('value', 18, 4);
            $table->decimal('numerator', 18, 4)->nullable();
            $table->decimal('denominator', 18, 4)->nullable();
            $table->json('dimensions')->nullable();
            $table->string('period_key', 80)->default('all-time');
            $table->char('dimensions_hash', 64);
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->index(['company_id', 'metric_key', 'calculated_at'], 'tz_fb_metrics_company_key_time_ix');
            $table->unique(['company_id', 'metric_key', 'period_key', 'dimensions_hash'], 'tz_fb_metrics_projection_uq');
        });
    }

    private function extendLegacyRebooking(): void
    {
        if (Schema::hasTable('tz_rebooking_requests') && ! Schema::hasColumn('tz_rebooking_requests', 'feedback_item_id')) {
            Schema::table('tz_rebooking_requests', static function (Blueprint $table): void {
                $table->unsignedBigInteger('feedback_item_id')->nullable()->index()->after('review_id');
            });
        }
    }

    private function migrateLegacyReviews(): void
    {
        if (Schema::hasTable('tz_review_requests')) {
            foreach (DB::table('tz_review_requests')->orderBy('id')->get() as $legacy) {
                $targetId = $this->mappedTargetId((int) $legacy->company_id, 'tz_review_requests', (string) $legacy->id);
                if ($targetId !== null) {
                    continue;
                }

                $publicId = $this->safePublicId($legacy->public_id ?? null);
                $targetId = (int) DB::table('tz_feedback_requests')->insertGetId([
                    'public_id' => $publicId,
                    'company_id' => (int) $legacy->company_id,
                    'survey_id' => null,
                    'customer_id' => $legacy->customer_id,
                    'contact_id' => $legacy->contact_id,
                    'premises_id' => $legacy->premises_id,
                    'work_order_id' => $legacy->work_order_id,
                    'appointment_id' => $legacy->appointment_id,
                    'worker_id' => $legacy->worker_id,
                    'requested_by_user_id' => $legacy->created_by_user_id,
                    'audience' => $legacy->audience ?? 'customer',
                    'channel' => $legacy->channel ?? 'web',
                    'source_ref_type' => 'legacy_review_request',
                    'source_ref_id' => (string) $legacy->id,
                    'dedupe_key' => 'legacy:tz_review_requests:' . $legacy->id,
                    'status' => $legacy->status ?? 'pending',
                    'sent_at' => $legacy->requested_at,
                    'completed_at' => $legacy->responded_at,
                    'expires_at' => $legacy->expires_at,
                    'metadata' => $legacy->metadata,
                    'created_at' => $legacy->created_at ?? now(),
                    'updated_at' => $legacy->updated_at ?? now(),
                ]);
                $this->recordMapping((int) $legacy->company_id, 'tz_review_requests', (string) $legacy->id, 'feedback_request', $targetId, $legacy);
            }
        }

        if (Schema::hasTable('tz_reviews')) {
            foreach (DB::table('tz_reviews')->orderBy('id')->get() as $legacy) {
                if ($this->mappedTargetId((int) $legacy->company_id, 'tz_reviews', (string) $legacy->id) !== null) {
                    continue;
                }

                $requestId = $legacy->review_request_id === null
                    ? null
                    : $this->mappedTargetId((int) $legacy->company_id, 'tz_review_requests', (string) $legacy->review_request_id);
                $targetId = (int) DB::table('tz_feedback_items')->insertGetId([
                    'public_id' => $this->safePublicId($legacy->public_id ?? null),
                    'company_id' => (int) $legacy->company_id,
                    'feedback_request_id' => $requestId,
                    'customer_id' => $legacy->customer_id,
                    'contact_id' => $legacy->contact_id,
                    'premises_id' => $legacy->premises_id,
                    'work_order_id' => $legacy->work_order_id,
                    'appointment_id' => $legacy->appointment_id,
                    'worker_id' => $legacy->worker_id,
                    'requester_user_id' => $legacy->created_by_user_id,
                    'kind' => 'review',
                    'visibility' => ($legacy->publication_status ?? 'private') === 'published' ? 'public' : 'private',
                    'body' => $legacy->comment,
                    'rating' => $legacy->rating,
                    'sentiment' => $legacy->sentiment ?? 'neutral',
                    'status' => 'closed',
                    'moderation_status' => ($legacy->publication_status ?? 'private') === 'published' ? 'approved' : 'not_required',
                    'publication_status' => $legacy->publication_status ?? 'private',
                    'consent_status' => (bool) ($legacy->public_permission ?? false) ? 'granted' : 'unknown',
                    'public_permission' => (bool) ($legacy->public_permission ?? false),
                    'published_at' => $legacy->published_at,
                    'source_ref_type' => 'legacy_review',
                    'source_ref_id' => (string) $legacy->id,
                    'dedupe_key' => 'legacy:tz_reviews:' . $legacy->id,
                    'custom_meta' => $legacy->metadata,
                    'created_at' => $legacy->created_at ?? now(),
                    'updated_at' => $legacy->updated_at ?? now(),
                    'deleted_at' => $legacy->deleted_at,
                ]);
                $this->recordMapping((int) $legacy->company_id, 'tz_reviews', (string) $legacy->id, 'feedback_item', $targetId, $legacy);
            }
        }

        if (Schema::hasTable('tz_service_recovery_cases')) {
            foreach (DB::table('tz_service_recovery_cases')->orderBy('id')->get() as $legacy) {
                if ($this->mappedTargetId((int) $legacy->company_id, 'tz_service_recovery_cases', (string) $legacy->id) !== null) {
                    continue;
                }
                $feedbackItemId = $this->mappedTargetId((int) $legacy->company_id, 'tz_reviews', (string) $legacy->review_id);
                if ($feedbackItemId === null) {
                    continue;
                }
                $status = match ((string) ($legacy->status ?? 'open')) {
                    'resolved', 'closed', 'completed' => 'completed',
                    'cancelled' => 'cancelled',
                    default => 'in_progress',
                };
                $targetId = (int) DB::table('tz_feedback_service_recoveries')->insertGetId([
                    'public_id' => $this->safePublicId($legacy->public_id ?? null),
                    'company_id' => (int) $legacy->company_id,
                    'feedback_item_id' => $feedbackItemId,
                    'support_ticket_id' => $legacy->support_ticket_id,
                    'work_order_id' => $legacy->work_order_id,
                    'owner_user_id' => $legacy->assigned_user_id,
                    'action_type' => 'service_recovery',
                    'status' => $status,
                    'severity' => $legacy->severity ?? 'medium',
                    'requires_approval' => false,
                    'completed_at' => $legacy->resolved_at,
                    'outcome' => $legacy->resolution,
                    'metadata' => json_encode(['legacy_reason_present' => trim((string) ($legacy->reason ?? '')) !== '', 'legacy_reason_hash' => trim((string) ($legacy->reason ?? '')) === '' ? null : hash('sha256', (string) $legacy->reason)], JSON_THROW_ON_ERROR),
                    'created_at' => $legacy->created_at ?? now(),
                    'updated_at' => $legacy->updated_at ?? now(),
                ]);
                $this->recordMapping((int) $legacy->company_id, 'tz_service_recovery_cases', (string) $legacy->id, 'service_recovery', $targetId, $legacy);
            }
        }

        if (Schema::hasTable('tz_rebooking_requests') && Schema::hasColumn('tz_rebooking_requests', 'feedback_item_id')) {
            foreach (DB::table('tz_rebooking_requests')->whereNotNull('review_id')->whereNull('feedback_item_id')->get() as $legacy) {
                $feedbackItemId = $this->mappedTargetId((int) $legacy->company_id, 'tz_reviews', (string) $legacy->review_id);
                if ($feedbackItemId !== null) {
                    DB::table('tz_rebooking_requests')->where('id', $legacy->id)->update(['feedback_item_id' => $feedbackItemId]);
                }
            }
        }
    }

    private function mappedTargetId(int $companyId, string $sourceTable, string $sourceId): ?int
    {
        $value = DB::table('tz_feedback_legacy_mappings')
            ->where('company_id', $companyId)
            ->where('source_module', 'workcore.reviews')
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->value('target_id');

        return $value === null ? null : (int) $value;
    }

    private function recordMapping(int $companyId, string $sourceTable, string $sourceId, string $targetType, int $targetId, object $legacy): void
    {
        DB::table('tz_feedback_legacy_mappings')->insert([
            'company_id' => $companyId,
            'source_module' => 'workcore.reviews',
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'source_checksum' => hash('sha256', json_encode((array) $legacy, JSON_THROW_ON_ERROR)),
            'imported_at' => now(),
            'metadata' => json_encode(['migration' => 'pass35'], JSON_THROW_ON_ERROR),
        ]);
    }

    private function safePublicId(mixed $value): string
    {
        $candidate = is_string($value) ? trim($value) : '';

        return $candidate !== '' && ! DB::table('tz_feedback_items')->where('public_id', $candidate)->exists()
            && ! DB::table('tz_feedback_requests')->where('public_id', $candidate)->exists()
            && ! DB::table('tz_feedback_service_recoveries')->where('public_id', $candidate)->exists()
                ? $candidate
                : (string) Str::ulid();
    }
};

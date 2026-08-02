<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tz_review_requests', function (Blueprint $t): void {
            $t->id(); $t->ulid('public_id')->unique(); $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('customer_id')->nullable()->index(); $t->unsignedBigInteger('contact_id')->nullable()->index();
            $t->unsignedBigInteger('premises_id')->nullable()->index(); $t->unsignedBigInteger('work_order_id')->nullable()->index();
            $t->unsignedBigInteger('appointment_id')->nullable()->index(); $t->unsignedBigInteger('worker_id')->nullable()->index();
            $t->string('audience',32)->default('customer'); $t->string('channel',32)->default('email');
            $t->string('status',32)->default('draft')->index(); $t->timestamp('requested_at')->nullable();
            $t->timestamp('expires_at')->nullable(); $t->timestamp('responded_at')->nullable(); $t->json('metadata')->nullable();
            $t->unsignedBigInteger('created_by_user_id'); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('tz_reviews', function (Blueprint $t): void {
            $t->id(); $t->ulid('public_id')->unique(); $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('review_request_id')->nullable()->index(); $t->unsignedBigInteger('customer_id')->nullable()->index();
            $t->unsignedBigInteger('contact_id')->nullable()->index(); $t->unsignedBigInteger('premises_id')->nullable()->index();
            $t->unsignedBigInteger('work_order_id')->nullable()->index(); $t->unsignedBigInteger('appointment_id')->nullable()->index();
            $t->unsignedBigInteger('worker_id')->nullable()->index(); $t->unsignedTinyInteger('rating');
            $t->string('sentiment',32)->default('neutral')->index(); $t->text('comment')->nullable();
            $t->boolean('public_permission')->default(false); $t->string('publication_status',32)->default('private')->index();
            $t->timestamp('published_at')->nullable(); $t->json('metadata')->nullable(); $t->unsignedBigInteger('created_by_user_id');
            $t->timestamps(); $t->softDeletes();
        });
        Schema::create('tz_service_recovery_cases', function (Blueprint $t): void {
            $t->id(); $t->ulid('public_id')->unique(); $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('review_id')->index(); $t->unsignedBigInteger('support_ticket_id')->nullable()->index();
            $t->unsignedBigInteger('work_order_id')->nullable()->index(); $t->string('status',32)->default('open')->index();
            $t->string('severity',32)->default('medium'); $t->text('reason'); $t->text('resolution')->nullable();
            $t->unsignedBigInteger('assigned_user_id')->nullable(); $t->timestamp('resolved_at')->nullable();
            $t->unsignedBigInteger('created_by_user_id'); $t->timestamps();
        });
        Schema::create('tz_rebooking_requests', function (Blueprint $t): void {
            $t->id(); $t->ulid('public_id')->unique(); $t->unsignedBigInteger('company_id')->index();
            $t->unsignedBigInteger('review_id')->nullable()->index(); $t->unsignedBigInteger('customer_id')->nullable()->index();
            $t->unsignedBigInteger('premises_id')->nullable()->index(); $t->unsignedBigInteger('service_id')->nullable()->index();
            $t->unsignedBigInteger('source_work_order_id')->nullable()->index(); $t->unsignedBigInteger('created_work_order_id')->nullable()->index();
            $t->unsignedBigInteger('created_appointment_id')->nullable()->index(); $t->string('status',32)->default('requested')->index();
            $t->timestamp('preferred_start_at')->nullable(); $t->timestamp('preferred_end_at')->nullable(); $t->string('timezone',64)->nullable();
            $t->text('notes')->nullable(); $t->json('metadata')->nullable(); $t->unsignedBigInteger('created_by_user_id'); $t->timestamps();
        });
        Schema::create('tz_review_status_history', function (Blueprint $t): void {
            $t->id(); $t->ulid('public_id')->unique(); $t->unsignedBigInteger('company_id')->index();
            $t->string('subject_type',40); $t->unsignedBigInteger('subject_id'); $t->string('from_status',32)->nullable();
            $t->string('to_status',32); $t->text('reason')->nullable(); $t->unsignedBigInteger('actor_user_id'); $t->timestamp('changed_at'); $t->timestamps();
            $t->index(['company_id','subject_type','subject_id'], 'tz_review_subject_lookup_idx');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tz_review_status_history'); Schema::dropIfExists('tz_rebooking_requests');
        Schema::dropIfExists('tz_service_recovery_cases'); Schema::dropIfExists('tz_reviews'); Schema::dropIfExists('tz_review_requests');
    }
};

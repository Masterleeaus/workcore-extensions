<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_adaptive_features', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('adaptive_domain_id')->nullable()->constrained('tz_adaptive_domains')->nullOnDelete();
            $table->char('source_request_public_id', 26)->nullable()->index();
            $table->string('slug', 80);
            $table->string('name', 160);
            $table->string('capability_key', 128);
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('trigger_event', 128)->index();
            $table->string('subject_type', 64)->index();
            $table->string('domain_key', 128)->nullable()->index();
            $table->string('risk', 20)->default('medium');
            $table->string('status', 30)->default('draft')->index();
            $table->json('blueprint');
            $table->unsignedBigInteger('created_by_user_id')->index();
            $table->unsignedBigInteger('updated_by_user_id')->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'slug'], 'tz_adaptive_feature_company_slug_uq');
            $table->unique(['company_id', 'capability_key'], 'tz_adaptive_feature_company_capability_uq');
            $table->index(['company_id', 'status', 'trigger_event'], 'tz_adaptive_feature_dispatch_idx');
        });

        Schema::create('tz_adaptive_feature_versions', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('adaptive_feature_id')->constrained('tz_adaptive_features')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('change_type', 40);
            $table->char('source_request_public_id', 26)->nullable()->index();
            $table->json('blueprint');
            $table->unsignedBigInteger('created_by_user_id')->index();
            $table->timestamp('created_at');
            $table->unique(['adaptive_feature_id', 'version'], 'tz_adaptive_feature_version_uq');
        });

        Schema::create('tz_adaptive_feature_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('adaptive_feature_id')->constrained('tz_adaptive_features')->cascadeOnDelete();
            $table->unsignedInteger('feature_version');
            $table->string('trigger_event', 128)->index();
            $table->string('subject_type', 64)->index();
            $table->string('subject_public_id', 128)->index();
            $table->char('idempotency_key', 64);
            $table->unsignedSmallInteger('execution_depth')->default(0);
            $table->string('status', 40)->default('running')->index();
            $table->json('input_context');
            $table->json('simulation');
            $table->json('output');
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('actor_id')->index();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key'], 'tz_adaptive_feature_run_idempotency_uq');
            $table->index(['company_id', 'adaptive_feature_id', 'started_at'], 'tz_adaptive_feature_run_rate_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_adaptive_feature_runs');
        Schema::dropIfExists('tz_adaptive_feature_versions');
        Schema::dropIfExists('tz_adaptive_features');
    }
};

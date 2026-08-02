<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_capability_expansion_requests', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->unsignedBigInteger('requested_by_user_id')->index();
            $table->text('requirement');
            $table->text('normalised_requirement');
            $table->json('assessment');
            $table->json('plan');
            $table->string('delivery_mode', 40)->index();
            $table->string('risk', 20)->default('low')->index();
            $table->string('status', 40)->default('proposed')->index();
            $table->unsignedBigInteger('approved_by_user_id')->nullable()->index();
            $table->text('approval_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status'], 'tz_expansion_company_status_idx');
        });

        Schema::create('tz_adaptive_domains', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->char('source_request_public_id', 26)->nullable()->index();
            $table->string('slug', 56);
            $table->string('name', 120);
            $table->string('capability_key', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('risk', 20)->default('low');
            $table->string('status', 30)->default('draft')->index();
            $table->json('blueprint');
            $table->unsignedBigInteger('created_by_user_id')->index();
            $table->unsignedBigInteger('updated_by_user_id')->index();
            $table->timestamps();
            $table->unique(['company_id', 'slug'], 'tz_adaptive_domain_company_slug_uq');
            $table->unique(['company_id', 'capability_key'], 'tz_adaptive_domain_company_capability_uq');
        });

        Schema::create('tz_adaptive_domain_versions', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('adaptive_domain_id')->constrained('tz_adaptive_domains')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('change_type', 40);
            $table->char('source_request_public_id', 26)->nullable()->index();
            $table->json('blueprint');
            $table->unsignedBigInteger('created_by_user_id')->index();
            $table->timestamp('created_at');
            $table->unique(['adaptive_domain_id', 'version'], 'tz_adaptive_domain_version_uq');
            $table->index(['company_id', 'adaptive_domain_id', 'version'], 'tz_adaptive_domain_version_lookup_idx');
        });

        Schema::create('tz_adaptive_records', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('adaptive_domain_id')->constrained('tz_adaptive_domains')->cascadeOnDelete();
            $table->string('status', 40)->index();
            $table->json('data');
            $table->unsignedBigInteger('created_by_user_id')->index();
            $table->unsignedBigInteger('updated_by_user_id')->index();
            $table->timestamps();
            $table->index(['company_id', 'adaptive_domain_id', 'status'], 'tz_adaptive_record_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_adaptive_records');
        Schema::dropIfExists('tz_adaptive_domain_versions');
        Schema::dropIfExists('tz_adaptive_domains');
        Schema::dropIfExists('tz_capability_expansion_requests');
    }
};

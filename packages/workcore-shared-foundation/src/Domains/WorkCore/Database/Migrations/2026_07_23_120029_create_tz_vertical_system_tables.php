<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_company_verticals', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('vertical_key', 100);
            $table->string('pack_version', 40)->default('1.0.0');
            $table->boolean('is_primary')->default(false);
            $table->string('status', 30)->default('active');
            $table->json('settings')->nullable();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'vertical_key'], 'tz_company_vertical_unique');
            $table->index(['company_id', 'is_primary', 'status'], 'tz_company_vertical_primary_idx');
        });

        Schema::create('tz_business_lines', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('company_vertical_id')->nullable()->constrained('tz_company_verticals')->nullOnDelete();
            $table->string('key', 100);
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->string('status', 30)->default('active');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'key'], 'tz_business_line_key_unique');
            $table->index(['company_id', 'status', 'is_default'], 'tz_business_line_status_idx');
        });

        Schema::create('tz_company_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('scope_type', 30)->default('company');
            $table->unsignedBigInteger('scope_id');
            $table->string('feature_key', 160);
            $table->boolean('enabled');
            $table->string('source', 40)->default('company_override');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'scope_type', 'scope_id', 'feature_key'], 'tz_company_feature_scope_unique');
            $table->index(['company_id', 'feature_key', 'enabled'], 'tz_company_feature_lookup_idx');
        });

        Schema::create('tz_company_terminology_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('scope_type', 30)->default('company');
            $table->unsignedBigInteger('scope_id');
            $table->string('locale', 20)->default('*');
            $table->string('term_key', 160);
            $table->text('term_value');
            $table->timestamps();

            $table->unique(['company_id', 'scope_type', 'scope_id', 'locale', 'term_key'], 'tz_company_term_scope_unique');
            $table->index(['company_id', 'term_key'], 'tz_company_term_lookup_idx');
        });

        Schema::create('tz_vertical_installations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('vertical_key', 100);
            $table->string('installed_version', 40);
            $table->string('status', 30)->default('installed');
            $table->char('manifest_hash', 64)->nullable();
            $table->foreignId('installed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('upgraded_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'vertical_key'], 'tz_vertical_installation_unique');
            $table->index(['company_id', 'status'], 'tz_vertical_installation_status_idx');
        });

        Schema::create('tz_vertical_seed_provenance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('vertical_key', 100);
            $table->string('seed_key', 160);
            $table->string('record_type', 100);
            $table->unsignedBigInteger('record_id');
            $table->string('installed_version', 40);
            $table->char('content_hash', 64)->nullable();
            $table->boolean('is_customer_modified')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'vertical_key', 'seed_key', 'record_type'], 'tz_vertical_seed_unique');
            $table->index(['company_id', 'record_type', 'record_id'], 'tz_vertical_seed_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_vertical_seed_provenance');
        Schema::dropIfExists('tz_vertical_installations');
        Schema::dropIfExists('tz_company_terminology_overrides');
        Schema::dropIfExists('tz_company_features');
        Schema::dropIfExists('tz_business_lines');
        Schema::dropIfExists('tz_company_verticals');
    }
};

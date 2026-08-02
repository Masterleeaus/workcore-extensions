<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_vertical_setup_sessions', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('primary_vertical_key', 100);
            $table->json('add_on_vertical_keys')->nullable();
            $table->string('status', 30)->default('previewed');
            $table->json('answers')->nullable();
            $table->json('compiled_plan')->nullable();
            $table->char('plan_hash', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'actor_user_id'], 'tz_vertical_setup_status_idx');
            $table->index(['company_id', 'plan_hash'], 'tz_vertical_setup_hash_idx');
        });

        Schema::create('tz_company_locales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('locale', 20);
            $table->string('display_name', 100);
            $table->string('direction', 3)->default('ltr');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('source', 40)->default('company');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'locale'], 'tz_company_locale_unique');
            $table->index(['company_id', 'is_default', 'is_active'], 'tz_company_locale_status_idx');
        });

        Schema::create('tz_company_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('setting_key', 191);
            $table->string('setting_group', 80)->default('vertical_setup');
            $table->json('value')->nullable();
            $table->string('source', 40)->default('company');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'setting_key'], 'tz_company_setting_unique');
            $table->index(['company_id', 'setting_group'], 'tz_company_setting_group_idx');
        });

        Schema::create('tz_company_document_templates', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('business_line_id')->nullable()->constrained('tz_business_lines')->nullOnDelete();
            $table->string('vertical_key', 100);
            $table->string('document_key', 160);
            $table->string('document_type', 60)->default('document');
            $table->string('title');
            $table->string('locale', 20)->default('en-AU');
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->string('source_version', 40)->default('1.0.0');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'document_key', 'locale'], 'tz_company_document_unique');
            $table->index(['company_id', 'vertical_key', 'document_type'], 'tz_company_document_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_company_document_templates');
        Schema::dropIfExists('tz_company_settings');
        Schema::dropIfExists('tz_company_locales');
        Schema::dropIfExists('tz_vertical_setup_sessions');
    }
};

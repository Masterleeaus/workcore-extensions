<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tz_company_entitlement_states')) {
            Schema::create('tz_company_entitlement_states', static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete()->unique();
                $table->unsignedBigInteger('revision')->default(1);
                $table->string('source', 80)->default('magicai_subscription');
                $table->string('source_subscription_id', 190)->nullable();
                $table->string('source_plan_id', 190)->nullable();
                $table->string('source_revision', 190)->nullable();
                $table->char('source_checksum', 64);
                $table->string('status', 40);
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('valid_until')->nullable();
                $table->timestamp('projected_at');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['status', 'valid_until'], 'tz_company_entitlement_state_validity_idx');
            });
        }

        if (! Schema::hasTable('tz_company_entitlement_projections')) {
            Schema::create('tz_company_entitlement_projections', static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->string('capability_key', 190);
                $table->unsignedBigInteger('revision');
                $table->boolean('enabled')->default(false);
                $table->string('source_feature_key', 500)->nullable();
                $table->timestamp('valid_until')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'capability_key'], 'tz_company_entitlement_capability_unique');
                $table->index(['company_id', 'revision', 'enabled'], 'tz_company_entitlement_revision_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_company_entitlement_projections');
        Schema::dropIfExists('tz_company_entitlement_states');
    }
};

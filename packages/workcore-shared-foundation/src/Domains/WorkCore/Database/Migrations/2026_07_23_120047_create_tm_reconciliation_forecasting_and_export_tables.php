<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tm_bank_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('name', 255);
            $table->string('account_type', 32);
            $table->char('currency_code', 3);
            $table->string('institution_hint', 255)->nullable();
            $table->string('masked_identifier', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tm_bank_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('bank_account_id')->index();
            $table->date('transaction_date')->index();
            $table->timestamp('observed_at')->nullable();
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('description', 500)->nullable();
            $table->string('reference_text', 500)->nullable();
            $table->string('payer_payee_hint', 255)->nullable();
            $table->char('transaction_hash', 64);
            $table->string('state', 32)->default('unmatched');
            $table->json('source_metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'transaction_hash'], 'tm_bank_transactions_hash_unique');
        });

        Schema::create('tm_reconciliations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('bank_account_id')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('opening_balance_minor');
            $table->bigInteger('statement_closing_balance_minor');
            $table->bigInteger('reconciled_closing_balance_minor')->default(0);
            $table->char('currency_code', 3);
            $table->string('state', 32)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_by_actor_type', 32)->nullable();
            $table->string('closed_by_actor_id', 128)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'bank_account_id', 'period_start', 'period_end'], 'tm_reconciliations_period_unique');
        });

        Schema::create('tm_reconciliation_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('reconciliation_id')->index();
            $table->uuid('bank_transaction_id')->index();
            $table->string('match_type', 32);
            $table->string('matched_entity_type', 32)->nullable();
            $table->string('matched_entity_id', 64)->nullable();
            $table->bigInteger('matched_amount_minor');
            $table->char('currency_code', 3);
            $table->unsignedTinyInteger('confidence_score')->default(0);
            $table->string('state', 32)->default('proposed');
            $table->string('confirmed_by_actor_type', 32)->nullable();
            $table->string('confirmed_by_actor_id', 128)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['reconciliation_id', 'bank_transaction_id'], 'tm_reconciliation_lines_transaction_unique');
        });

        Schema::create('tm_forecast_scenarios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('name', 255);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('model_version', 64);
            $table->string('state', 32)->default('draft');
            $table->json('assumptions');
            $table->json('projection');
            $table->char('currency_code', 3);
            $table->timestamp('generated_at');
            $table->timestamps();
        });

        Schema::create('tm_job_profitability_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('workcore_job_id', 64)->index();
            $table->bigInteger('revenue_minor');
            $table->bigInteger('labour_cost_minor');
            $table->bigInteger('material_cost_minor');
            $table->bigInteger('other_cost_minor');
            $table->bigInteger('profit_minor');
            $table->integer('margin_basis_points')->nullable();
            $table->char('currency_code', 3);
            $table->json('source_versions');
            $table->timestamp('calculated_at');
            $table->timestamps();
        });

        Schema::create('tm_accountant_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('format', 32);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('state', 32)->default('requested');
            $table->json('included_sections');
            $table->string('document_id', 128)->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->string('requested_by_actor_type', 32);
            $table->string('requested_by_actor_id', 128);
            $table->string('approval_id', 64)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_accountant_exports');
        Schema::dropIfExists('tm_job_profitability_snapshots');
        Schema::dropIfExists('tm_forecast_scenarios');
        Schema::dropIfExists('tm_reconciliation_lines');
        Schema::dropIfExists('tm_reconciliations');
        Schema::dropIfExists('tm_bank_transactions');
        Schema::dropIfExists('tm_bank_accounts');
    }
};

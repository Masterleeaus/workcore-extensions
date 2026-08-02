<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tm_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('account_code', 32);
            $table->string('name', 255);
            $table->string('role', 64);
            $table->string('type', 32);
            $table->char('currency_code', 3)->nullable();
            $table->boolean('is_control_account')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'account_code'], 'tm_accounts_code_unique');
            $table->unique(['company_id', 'role'], 'tm_accounts_role_unique');
        });

        Schema::create('tm_tax_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('code', 32);
            $table->string('name', 255);
            $table->string('jurisdiction_code', 32);
            $table->string('treatment', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'tm_tax_codes_company_code_unique');
        });

        Schema::create('tm_tax_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('tax_code_id')->index();
            $table->integer('rate_basis_points');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->unique(['tax_code_id', 'effective_from'], 'tm_tax_rates_effective_unique');
        });

        Schema::create('tm_accounting_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('period_key', 32);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('state', 32)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_by_actor_type', 32)->nullable();
            $table->string('closed_by_actor_id', 128)->nullable();
            $table->string('approval_id', 64)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'period_key'], 'tm_accounting_periods_key_unique');
        });

        Schema::create('tm_journal_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('journal_number', 64);
            $table->date('accounting_date')->index();
            $table->uuid('accounting_period_id')->nullable()->index();
            $table->string('source_type', 64);
            $table->string('source_id', 64);
            $table->string('state', 32)->default('posted');
            $table->char('currency_code', 3);
            $table->bigInteger('debit_total_minor');
            $table->bigInteger('credit_total_minor');
            $table->text('description')->nullable();
            $table->uuid('reversal_of_entry_id')->nullable();
            $table->string('created_by_actor_type', 32);
            $table->string('created_by_actor_id', 128);
            $table->string('origin', 32);
            $table->string('correlation_id', 128);
            $table->timestamps();
            $table->unique(['company_id', 'journal_number'], 'tm_journal_entries_number_unique');
            $table->unique(['company_id', 'source_type', 'source_id'], 'tm_journal_entries_source_unique');
        });

        Schema::create('tm_journal_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('journal_entry_id')->index();
            $table->uuid('account_id')->nullable()->index();
            $table->string('account_role', 64);
            $table->string('side', 16);
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('memo', 500)->nullable();
            $table->string('workcore_job_id', 64)->nullable()->index();
            $table->string('workcore_customer_id', 64)->nullable();
            $table->json('dimensions')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'account_role', 'created_at'], 'tm_journal_lines_role_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_journal_lines');
        Schema::dropIfExists('tm_journal_entries');
        Schema::dropIfExists('tm_accounting_periods');
        Schema::dropIfExists('tm_tax_rates');
        Schema::dropIfExists('tm_tax_codes');
        Schema::dropIfExists('tm_accounts');
    }
};

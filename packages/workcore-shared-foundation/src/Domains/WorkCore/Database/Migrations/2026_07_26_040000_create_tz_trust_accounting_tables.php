<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_trust_accounts', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('account_type', 30)->default('trust')->index();
            $table->char('currency', 3)->default('AUD');
            $table->string('bank_reference', 191)->nullable();
            $table->string('credential_reference', 255)->nullable();
            $table->boolean('is_operating_account')->default(false);
            $table->unsignedTinyInteger('required_approvals')->default(2);
            $table->boolean('active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('tz_trust_matters', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('trust_account_id')->constrained('tz_trust_accounts')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('tz_customers')->nullOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->char('agreement_public_id', 26)->nullable()->index();
            $table->string('matter_number', 80);
            $table->string('matter_type', 60)->index();
            $table->string('status', 30)->default('open')->index();
            $table->string('display_name', 191);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'matter_number'], 'tz_trust_matter_number_unique');
        });

        Schema::create('tz_trust_receipts', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('trust_account_id')->constrained('tz_trust_accounts')->restrictOnDelete();
            $table->foreignId('matter_id')->nullable()->constrained('tz_trust_matters')->nullOnDelete();
            $table->string('receipt_number', 80);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('AUD');
            $table->string('payer_name', 191);
            $table->string('reference', 191)->nullable();
            $table->dateTime('received_at');
            $table->string('status', 30)->default('received')->index();
            $table->char('source_observation_public_id', 26)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'receipt_number'], 'tz_trust_receipt_number_unique');
        });

        Schema::create('tz_trust_allocations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('receipt_id')->constrained('tz_trust_receipts')->restrictOnDelete();
            $table->foreignId('matter_id')->constrained('tz_trust_matters')->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('AUD');
            $table->foreignId('reversal_of_allocation_id')->nullable()->constrained('tz_trust_allocations')->nullOnDelete();
            $table->text('purpose')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tz_trust_disbursements', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('trust_account_id')->constrained('tz_trust_accounts')->restrictOnDelete();
            $table->foreignId('matter_id')->constrained('tz_trust_matters')->restrictOnDelete();
            $table->string('disbursement_number', 80);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('AUD');
            $table->string('payee_name', 191);
            $table->string('status', 30)->default('pending_approval')->index();
            $table->unsignedTinyInteger('required_approvals')->default(2);
            $table->text('purpose');
            $table->string('destination_reference', 191)->nullable();
            $table->dateTime('released_at')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'disbursement_number'], 'tz_trust_disbursement_number_unique');
        });

        Schema::create('tz_trust_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('disbursement_id')->constrained('tz_trust_disbursements')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20);
            $table->text('reason')->nullable();
            $table->dateTime('decided_at');
            $table->timestamps();
            $table->unique(['disbursement_id', 'approved_by_user_id'], 'tz_trust_approval_actor_unique');
        });

        Schema::create('tz_trust_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('trust_account_id')->constrained('tz_trust_accounts')->restrictOnDelete();
            $table->foreignId('matter_id')->nullable()->constrained('tz_trust_matters')->nullOnDelete();
            $table->string('entry_type', 40)->index();
            $table->string('source_type', 60);
            $table->char('source_public_id', 26)->nullable();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('AUD');
            $table->foreignId('reversal_of_entry_id')->nullable()->constrained('tz_trust_ledger_entries')->nullOnDelete();
            $table->dateTime('posted_at');
            $table->text('description')->nullable();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'trust_account_id', 'posted_at'], 'tz_trust_ledger_account_idx');
            $table->index(['company_id', 'matter_id', 'posted_at'], 'tz_trust_ledger_matter_idx');
        });

        Schema::create('tz_trust_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('trust_account_id')->constrained('tz_trust_accounts')->restrictOnDelete();
            $table->date('statement_date');
            $table->bigInteger('statement_balance_minor');
            $table->bigInteger('ledger_balance_minor');
            $table->bigInteger('difference_minor');
            $table->char('currency', 3)->default('AUD');
            $table->string('status', 30)->default('draft')->index();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'trust_account_id', 'statement_date'], 'tz_trust_reconcile_date_unique');
        });

        Schema::create('tz_trust_reconciliation_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('reconciliation_id')->constrained('tz_trust_reconciliations')->restrictOnDelete();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('tz_trust_ledger_entries')->nullOnDelete();
            $table->string('line_type', 40);
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('AUD');
            $table->string('reference', 191)->nullable();
            $table->boolean('matched')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'tz_trust_reconciliation_lines',
            'tz_trust_reconciliations',
            'tz_trust_ledger_entries',
            'tz_trust_approvals',
            'tz_trust_disbursements',
            'tz_trust_allocations',
            'tz_trust_receipts',
            'tz_trust_matters',
            'tz_trust_accounts',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

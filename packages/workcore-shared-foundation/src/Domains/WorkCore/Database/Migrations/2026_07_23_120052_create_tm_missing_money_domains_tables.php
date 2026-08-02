<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $audit = static function (Blueprint $table): void {
            $table->string('created_by_user_id', 64)->nullable();
            $table->string('updated_by_user_id', 64)->nullable();
            $table->string('created_by_actor_type', 32);
            $table->string('created_by_actor_id', 128);
            $table->string('updated_by_actor_type', 32);
            $table->string('updated_by_actor_id', 128);
            $table->string('origin', 32);
            $table->string('correlation_id', 128);
            $table->timestamps();
        };

        Schema::create('tm_budgets', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('name', 255);
            $table->string('state', 32)->default('draft');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->bigInteger('allocated_minor')->default(0);
            $table->bigInteger('committed_minor')->default(0);
            $table->bigInteger('actual_minor')->default(0);
            $table->char('currency_code', 3);
            $table->string('approved_by_actor_type', 32)->nullable();
            $table->string('approved_by_actor_id', 128)->nullable();
            $table->timestamp('approved_at')->nullable();
            $audit($table);
            $table->index(['company_id', 'state', 'starts_on', 'ends_on'], 'tm_budgets_company_period_index');
        });

        Schema::create('tm_budget_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('budget_id')->index();
            $table->string('category_code', 64);
            $table->string('cost_centre_code', 64)->nullable();
            $table->string('workcore_premises_id', 64)->nullable()->index();
            $table->string('workcore_service_id', 64)->nullable();
            $table->bigInteger('allocated_minor')->default(0);
            $table->bigInteger('committed_minor')->default(0);
            $table->bigInteger('actual_minor')->default(0);
            $table->char('currency_code', 3);
            $table->timestamps();
            $table->unique(['budget_id', 'category_code', 'cost_centre_code'], 'tm_budget_lines_scope_unique');
        });

        Schema::create('tm_budget_approval_thresholds', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('action_type', 64);
            $table->bigInteger('minimum_minor');
            $table->char('currency_code', 3);
            $table->string('required_permission', 128);
            $table->boolean('active')->default(true);
            $audit($table);
            $table->index(['company_id', 'action_type', 'minimum_minor'], 'tm_budget_threshold_lookup_index');
        });

        Schema::create('tm_budget_actuals', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('budget_id')->index();
            $table->uuid('budget_line_id')->nullable()->index();
            $table->string('source_type', 64);
            $table->string('source_id', 64);
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->date('posted_on');
            $audit($table);
            $table->unique(['company_id', 'source_type', 'source_id'], 'tm_budget_actual_source_unique');
        });

        Schema::create('tm_budget_variances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('budget_id')->index();
            $table->uuid('budget_line_id')->nullable()->index();
            $table->bigInteger('budget_minor');
            $table->bigInteger('actual_minor');
            $table->bigInteger('variance_minor');
            $table->integer('variance_basis_points')->default(0);
            $table->string('severity', 32)->default('normal');
            $table->date('period_ends_on');
            $table->timestamps();
        });

        Schema::create('tm_budget_forecast_scenarios', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('budget_id')->nullable()->index();
            $table->string('name', 255);
            $table->string('scenario_type', 32)->default('baseline');
            $table->json('assumptions');
            $table->json('forecast_periods');
            $table->string('state', 32)->default('draft');
            $audit($table);
        });

        Schema::create('tm_expense_claims', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('claimant_id', 64)->index();
            $table->string('workcore_job_id', 64)->nullable()->index();
            $table->string('category_code', 64);
            $table->string('state', 32)->default('draft');
            $table->date('incurred_on');
            $table->text('description')->nullable();
            $table->bigInteger('amount_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->char('currency_code', 3);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by_actor_type', 32)->nullable();
            $table->string('approved_by_actor_id', 128)->nullable();
            $table->timestamp('reimbursed_at')->nullable();
            $audit($table);
            $table->index(['company_id', 'claimant_id', 'state'], 'tm_expense_claims_claimant_state_index');
        });

        Schema::create('tm_expense_claim_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('expense_claim_id')->index();
            $table->string('document_id', 128);
            $table->string('file_name', 255);
            $table->string('mime_type', 128);
            $table->char('content_hash', 64);
            $table->string('ocr_status', 32)->default('pending');
            $table->json('extracted_data')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'content_hash'], 'tm_expense_claim_receipts_hash_unique');
        });

        Schema::create('tm_reimbursement_batches', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('batch_number', 64);
            $table->string('state', 32)->default('draft');
            $table->bigInteger('total_minor')->default(0);
            $table->char('currency_code', 3);
            $table->uuid('payment_id')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $audit($table);
            $table->unique(['company_id', 'batch_number'], 'tm_reimbursement_batches_number_unique');
        });

        Schema::create('tm_reimbursements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('reimbursement_batch_id')->index();
            $table->uuid('expense_claim_id')->index();
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->timestamps();
            $table->unique(['reimbursement_batch_id', 'expense_claim_id'], 'tm_reimbursements_claim_unique');
        });

        Schema::create('tm_purchase_orders', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('supplier_id')->index();
            $table->string('purchase_order_number', 64);
            $table->string('state', 32)->default('draft');
            $table->date('ordered_on')->nullable();
            $table->date('expected_on')->nullable();
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->char('currency_code', 3);
            $table->string('workcore_premises_id', 64)->nullable()->index();
            $table->string('workcore_job_id', 64)->nullable()->index();
            $table->uuid('budget_id')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $audit($table);
            $table->unique(['company_id', 'purchase_order_number'], 'tm_purchase_orders_number_unique');
        });

        Schema::create('tm_purchase_order_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('purchase_order_id')->index();
            $table->string('inventory_item_id', 64)->nullable()->index();
            $table->string('description', 500);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('received_quantity')->default(0);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('line_total_minor');
            $table->char('currency_code', 3);
            $table->timestamps();
        });

        Schema::create('tm_purchase_receipts', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('purchase_order_id')->index();
            $table->string('receipt_number', 64);
            $table->timestamp('received_at');
            $table->string('received_by_actor_type', 32);
            $table->string('received_by_actor_id', 128);
            $table->text('notes')->nullable();
            $audit($table);
            $table->unique(['company_id', 'receipt_number'], 'tm_purchase_receipts_number_unique');
        });

        Schema::create('tm_purchase_receipt_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('purchase_receipt_id')->index();
            $table->uuid('purchase_order_line_id')->index();
            $table->unsignedInteger('quantity_received');
            $table->string('stock_location_id', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('tm_supplier_credits', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('supplier_id')->index();
            $table->uuid('supplier_bill_id')->nullable()->index();
            $table->string('supplier_credit_number', 64);
            $table->string('state', 32)->default('draft');
            $table->bigInteger('amount_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->char('currency_code', 3);
            $table->timestamp('issued_at')->nullable();
            $audit($table);
            $table->unique(['company_id', 'supplier_credit_number'], 'tm_supplier_credits_number_unique');
        });

        Schema::create('tm_einvoice_profiles', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('network', 32)->default('peppol');
            $table->string('sender_endpoint', 255);
            $table->string('sender_scheme', 32)->nullable();
            $table->string('access_point_reference', 128)->nullable();
            $table->boolean('enabled')->default(false);
            $table->json('settings')->nullable();
            $audit($table);
            $table->unique(['company_id', 'network', 'sender_endpoint'], 'tm_einvoice_profiles_endpoint_unique');
        });

        Schema::create('tm_einvoice_transmissions', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('invoice_id')->index();
            $table->uuid('einvoice_profile_id')->nullable()->index();
            $table->string('format', 64);
            $table->string('receiver_endpoint', 255);
            $table->string('receiver_scheme', 32)->nullable();
            $table->char('document_hash', 64);
            $table->string('state', 32)->default('draft');
            $table->string('provider_message_id', 255)->nullable();
            $table->string('rejection_code', 128)->nullable();
            $table->text('rejection_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $audit($table);
            $table->unique(['company_id', 'invoice_id', 'document_hash'], 'tm_einvoice_transmissions_document_unique');
        });

        Schema::create('tm_einvoice_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('einvoice_transmission_id')->index();
            $table->string('event_type', 64);
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('tm_workforce_loans', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('worker_id', 64)->index();
            $table->string('loan_type_code', 64)->nullable();
            $table->string('state', 32)->default('draft');
            $table->bigInteger('principal_minor');
            $table->bigInteger('outstanding_minor');
            $table->bigInteger('scheduled_repayment_minor');
            $table->char('currency_code', 3);
            $table->uuid('disbursement_payment_id')->nullable()->index();
            $table->date('first_repayment_on')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('repaid_at')->nullable();
            $audit($table);
            $table->index(['company_id', 'worker_id', 'state'], 'tm_workforce_loans_worker_state_index');
        });

        Schema::create('tm_workforce_loan_repayments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('workforce_loan_id')->index();
            $table->uuid('payment_id')->nullable()->index();
            $table->string('external_reference', 255);
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->timestamp('repaid_at');
            $table->timestamps();
            $table->unique(['company_id', 'external_reference'], 'tm_workforce_loan_repayment_reference_unique');
        });

        Schema::create('tm_pos_sales', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('sale_number', 64);
            $table->string('state', 32)->default('draft');
            $table->string('customer_id', 64)->nullable()->index();
            $table->string('workcore_job_id', 64)->nullable()->index();
            $table->string('stock_location_id', 64)->nullable()->index();
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->bigInteger('paid_minor')->default(0);
            $table->bigInteger('change_minor')->default(0);
            $table->char('currency_code', 3);
            $table->timestamp('completed_at')->nullable();
            $audit($table);
            $table->unique(['company_id', 'sale_number'], 'tm_pos_sales_number_unique');
        });

        Schema::create('tm_pos_sale_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('pos_sale_id')->index();
            $table->string('product_id', 64)->nullable()->index();
            $table->string('description', 500);
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('line_total_minor');
            $table->char('currency_code', 3);
            $table->timestamps();
        });

        Schema::create('tm_pos_sale_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('pos_sale_id')->index();
            $table->uuid('payment_id')->nullable()->index();
            $table->string('method', 32);
            $table->string('reference', 255);
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->timestamps();
            $table->unique(['company_id', 'reference'], 'tm_pos_sale_payments_reference_unique');
        });

        Schema::create('tm_sales_orders', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('order_number', 64);
            $table->string('requester_id', 64)->index();
            $table->string('customer_id', 64)->nullable()->index();
            $table->string('workcore_job_id', 64)->nullable()->index();
            $table->string('state', 32)->default('draft');
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->char('currency_code', 3);
            $table->uuid('invoice_id')->nullable()->index();
            $table->timestamp('fulfilled_at')->nullable();
            $audit($table);
            $table->unique(['company_id', 'order_number'], 'tm_sales_orders_number_unique');
        });

        Schema::create('tm_sales_order_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('sales_order_id')->index();
            $table->string('product_id', 64)->nullable()->index();
            $table->string('description', 500);
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('line_total_minor');
            $table->char('currency_code', 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'tm_sales_order_lines',
            'tm_sales_orders',
            'tm_pos_sale_payments',
            'tm_pos_sale_lines',
            'tm_pos_sales',
            'tm_workforce_loan_repayments',
            'tm_workforce_loans',
            'tm_einvoice_events',
            'tm_einvoice_transmissions',
            'tm_einvoice_profiles',
            'tm_supplier_credits',
            'tm_purchase_receipt_lines',
            'tm_purchase_receipts',
            'tm_purchase_order_lines',
            'tm_purchase_orders',
            'tm_reimbursements',
            'tm_reimbursement_batches',
            'tm_expense_claim_receipts',
            'tm_expense_claims',
            'tm_budget_forecast_scenarios',
            'tm_budget_variances',
            'tm_budget_actuals',
            'tm_budget_approval_thresholds',
            'tm_budget_lines',
            'tm_budgets',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

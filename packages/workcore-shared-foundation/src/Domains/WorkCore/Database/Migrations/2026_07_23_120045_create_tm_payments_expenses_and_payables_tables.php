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

        Schema::create('tm_payment_requests', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('workcore_customer_id', 64);
            $table->uuid('invoice_id')->nullable()->index();
            $table->uuid('quote_id')->nullable();
            $table->string('purpose', 32)->default('invoice');
            $table->string('state', 32)->default('open');
            $table->string('preferred_method', 32)->nullable();
            $table->bigInteger('amount_minor');
            $table->bigInteger('received_minor')->default(0);
            $table->char('currency_code', 3);
            $table->string('payment_reference', 128);
            $table->timestamp('expires_at')->nullable();
            $audit($table);
            $table->unique(['company_id', 'payment_reference'], 'tm_payment_requests_reference_unique');
        });

        Schema::create('tm_payments', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('workcore_customer_id', 64)->nullable();
            $table->uuid('payment_request_id')->nullable()->index();
            $table->string('method', 32);
            $table->string('state', 32)->default('reported');
            $table->bigInteger('amount_minor');
            $table->bigInteger('allocated_minor')->default(0);
            $table->bigInteger('refunded_minor')->default(0);
            $table->char('currency_code', 3);
            $table->string('external_reference', 255)->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $audit($table);
            $table->index(['company_id', 'state', 'confirmed_at'], 'tm_payments_state_confirmed_index');
        });

        Schema::create('tm_payment_claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('payment_request_id')->nullable()->index();
            $table->string('claimed_by_actor_type', 32);
            $table->string('claimed_by_actor_id', 128);
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('reference_text', 500)->nullable();
            $table->string('state', 32)->default('pending_verification');
            $table->timestamp('claimed_at');
            $table->timestamps();
        });

        Schema::create('tm_payment_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('payment_claim_id')->nullable()->index();
            $table->uuid('payment_id')->nullable()->index();
            $table->string('source_type', 32);
            $table->string('source_device_id', 128)->nullable();
            $table->timestamp('observed_at');
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('reference_text', 500)->nullable();
            $table->string('payer_hint', 255)->nullable();
            $table->char('raw_payload_hash', 64);
            $table->unsignedTinyInteger('confidence_score')->default(0);
            $table->string('duplicate_status', 32)->default('unchecked');
            $table->string('verification_status', 32)->default('unverified');
            $table->timestamp('retention_expires_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'raw_payload_hash'], 'tm_payment_evidence_hash_unique');
        });

        Schema::create('tm_payment_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('payment_id')->index();
            $table->string('target_type', 32);
            $table->string('target_id', 64);
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('allocated_by_actor_type', 32);
            $table->string('allocated_by_actor_id', 128);
            $table->string('approval_id', 64)->nullable();
            $table->string('correlation_id', 128);
            $table->timestamp('allocated_at');
            $table->timestamps();
            $table->index(['company_id', 'target_type', 'target_id'], 'tm_payment_allocations_target_index');
        });

        Schema::create('tm_refunds', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('payment_id')->index();
            $table->string('state', 32)->default('requested');
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->text('reason');
            $table->string('approval_id', 64);
            $table->string('external_reference', 255)->nullable();
            $table->timestamp('completed_at')->nullable();
            $audit($table);
        });

        Schema::create('tm_credit_notes', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('invoice_id')->nullable()->index();
            $table->string('credit_note_number', 64);
            $table->string('state', 32)->default('draft');
            $table->bigInteger('amount_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->char('currency_code', 3);
            $table->text('reason');
            $table->timestamp('issued_at')->nullable();
            $audit($table);
            $table->unique(['company_id', 'credit_note_number'], 'tm_credit_notes_number_unique');
        });

        Schema::create('tm_suppliers', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('legal_name', 255);
            $table->string('trading_name', 255)->nullable();
            $table->string('abn', 32)->nullable();
            $table->string('contact_reference', 128)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(14);
            $table->string('default_expense_account_role', 64)->nullable();
            $table->string('state', 32)->default('active');
            $audit($table);
        });

        Schema::create('tm_supplier_bills', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('supplier_id')->index();
            $table->string('supplier_reference', 128)->nullable();
            $table->string('state', 32)->default('draft');
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->bigInteger('paid_minor')->default(0);
            $table->char('currency_code', 3);
            $table->string('workcore_job_id', 64)->nullable();
            $table->timestamp('approved_at')->nullable();
            $audit($table);
        });

        Schema::create('tm_supplier_bill_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('supplier_bill_id')->index();
            $table->unsignedInteger('position');
            $table->string('description', 500);
            $table->bigInteger('amount_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->char('currency_code', 3);
            $table->string('expense_account_role', 64)->nullable();
            $table->string('workcore_job_id', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('tm_expenses', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('supplier_id')->nullable()->index();
            $table->string('workcore_job_id', 64)->nullable()->index();
            $table->string('category_key', 64);
            $table->string('state', 32)->default('draft');
            $table->date('expense_date');
            $table->string('description', 500);
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->char('currency_code', 3);
            $table->boolean('receipt_required')->default(true);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approval_id', 64)->nullable();
            $audit($table);
        });

        Schema::create('tm_expense_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('expense_id')->index();
            $table->string('document_id', 128);
            $table->string('file_name', 255);
            $table->string('mime_type', 128);
            $table->string('ocr_status', 32)->default('pending');
            $table->json('extracted_data')->nullable();
            $table->char('content_hash', 64);
            $table->timestamps();
            $table->unique(['company_id', 'content_hash'], 'tm_expense_attachments_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_expense_attachments');
        Schema::dropIfExists('tm_expenses');
        Schema::dropIfExists('tm_supplier_bill_lines');
        Schema::dropIfExists('tm_supplier_bills');
        Schema::dropIfExists('tm_suppliers');
        Schema::dropIfExists('tm_credit_notes');
        Schema::dropIfExists('tm_refunds');
        Schema::dropIfExists('tm_payment_allocations');
        Schema::dropIfExists('tm_payment_evidence');
        Schema::dropIfExists('tm_payment_claims');
        Schema::dropIfExists('tm_payments');
        Schema::dropIfExists('tm_payment_requests');
    }
};

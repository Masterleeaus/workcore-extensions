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

        Schema::create('tm_estimates', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('workcore_customer_id', 64)->nullable();
            $table->string('workcore_site_id', 64)->nullable();
            $table->string('workcore_job_id', 64)->nullable();
            $table->string('estimate_number', 64);
            $table->unsignedInteger('current_version')->default(1);
            $table->string('state', 32)->default('draft');
            $table->char('currency_code', 3);
            $table->timestamp('approved_at')->nullable();
            $table->uuid('converted_quote_id')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $audit($table);
            $table->unique(['company_id', 'estimate_number'], 'tm_estimates_company_number_unique');
        });

        Schema::create('tm_estimate_versions', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('estimate_id')->index();
            $table->unsignedInteger('version');
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->char('currency_code', 3);
            $table->boolean('prices_include_tax')->default(true);
            $table->json('draft_snapshot');
            $table->text('scope')->nullable();
            $table->json('assumptions')->nullable();
            $table->char('snapshot_hash', 64);
            $audit($table);
            $table->unique(['company_id', 'estimate_id', 'version'], 'tm_estimate_versions_unique');
        });

        Schema::create('tm_estimate_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('estimate_version_id')->index();
            $table->unsignedInteger('position');
            $table->string('catalogue_item_id', 64)->nullable();
            $table->string('description', 500);
            $table->bigInteger('quantity_scaled');
            $table->unsignedTinyInteger('quantity_scale')->default(2);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->string('tax_code', 32)->nullable();
            $table->integer('tax_rate_basis_points')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('line_total_minor');
            $table->char('currency_code', 3);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['estimate_version_id', 'position'], 'tm_estimate_lines_position_unique');
        });

        Schema::create('tm_quotes', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('workcore_customer_id', 64);
            $table->string('workcore_site_id', 64)->nullable();
            $table->string('workcore_job_id', 64)->nullable();
            $table->uuid('source_estimate_id')->nullable();
            $table->string('quote_number', 64);
            $table->unsignedInteger('current_version')->default(1);
            $table->string('state', 32)->default('draft');
            $table->char('currency_code', 3);
            $table->date('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->string('converted_invoice_id', 64)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $audit($table);
            $table->unique(['company_id', 'quote_number'], 'tm_quotes_company_number_unique');
            $table->index(['company_id', 'state', 'valid_until'], 'tm_quotes_state_validity_index');
        });

        Schema::create('tm_quote_versions', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('quote_id')->index();
            $table->unsignedInteger('version');
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->char('currency_code', 3);
            $table->boolean('prices_include_tax')->default(true);
            $table->text('scope')->nullable();
            $table->text('terms')->nullable();
            $table->json('customer_snapshot');
            $table->json('seller_snapshot');
            $table->char('snapshot_hash', 64);
            $audit($table);
            $table->unique(['company_id', 'quote_id', 'version'], 'tm_quote_versions_unique');
        });

        Schema::create('tm_quote_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('quote_version_id')->index();
            $table->unsignedInteger('position');
            $table->string('catalogue_item_id', 64)->nullable();
            $table->string('description', 500);
            $table->bigInteger('quantity_scaled');
            $table->unsignedTinyInteger('quantity_scale')->default(2);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->string('tax_code', 32)->nullable();
            $table->integer('tax_rate_basis_points')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('line_total_minor');
            $table->char('currency_code', 3);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['quote_version_id', 'position'], 'tm_quote_lines_position_unique');
        });

        Schema::create('tm_quote_acceptances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('quote_id')->index();
            $table->uuid('quote_version_id');
            $table->string('decision', 32);
            $table->string('customer_actor_id', 128)->nullable();
            $table->string('signer_name', 255)->nullable();
            $table->string('signer_email', 255)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
        });

        Schema::create('tm_invoices', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('workcore_customer_id', 64);
            $table->string('workcore_site_id', 64)->nullable();
            $table->string('workcore_job_id', 64)->nullable();
            $table->string('source_type', 64)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->string('source_key', 191)->nullable();
            $table->uuid('source_quote_id')->nullable();
            $table->string('invoice_number', 64);
            $table->string('state', 32)->default('draft');
            $table->char('currency_code', 3);
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->bigInteger('allocated_minor')->default(0);
            $table->bigInteger('credited_minor')->default(0);
            $table->bigInteger('balance_minor')->default(0);
            $table->boolean('prices_include_tax')->default(true);
            $table->json('draft_snapshot');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->char('issued_snapshot_hash', 64)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $audit($table);
            $table->unique(['company_id', 'invoice_number'], 'tm_invoices_company_number_unique');
            $table->unique(['company_id', 'source_key'], 'tm_invoices_company_source_key_unique');
            $table->index(['company_id', 'state', 'due_date'], 'tm_invoices_state_due_index');
        });

        Schema::create('tm_invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('invoice_id')->index();
            $table->unsignedInteger('position');
            $table->string('source_type', 64)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->string('description', 500);
            $table->bigInteger('quantity_scaled');
            $table->unsignedTinyInteger('quantity_scale')->default(2);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->string('tax_code', 32)->nullable();
            $table->integer('tax_rate_basis_points')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('line_total_minor');
            $table->char('currency_code', 3);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['invoice_id', 'position'], 'tm_invoice_lines_position_unique');
        });

        Schema::create('tm_invoice_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('invoice_id')->index();
            $table->string('event_type', 64);
            $table->string('actor_type', 32);
            $table->string('actor_id', 128);
            $table->string('origin', 32);
            $table->string('correlation_id', 128);
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['company_id', 'event_type', 'occurred_at'], 'tm_invoice_events_type_time_index');
        });


        Schema::create('tm_recurring_invoice_rules', function (Blueprint $table) use ($audit): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('workcore_customer_id', 64);
            $table->string('workcore_site_id', 64)->nullable();
            $table->string('workcore_job_template_id', 64)->nullable();
            $table->string('name', 255);
            $table->string('frequency', 32);
            $table->unsignedInteger('interval_count')->default(1);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_issue_date')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(7);
            $table->char('currency_code', 3);
            $table->json('invoice_template');
            $table->string('state', 32)->default('active');
            $audit($table);
        });

        Schema::create('tm_customer_statements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('workcore_customer_id', 64)->index();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->bigInteger('opening_balance_minor')->default(0);
            $table->bigInteger('closing_balance_minor')->default(0);
            $table->char('currency_code', 3);
            $table->json('snapshot');
            $table->char('snapshot_hash', 64);
            $table->string('document_id', 128)->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_customer_statements');
        Schema::dropIfExists('tm_recurring_invoice_rules');
        Schema::dropIfExists('tm_invoice_events');
        Schema::dropIfExists('tm_invoice_lines');
        Schema::dropIfExists('tm_invoices');
        Schema::dropIfExists('tm_quote_acceptances');
        Schema::dropIfExists('tm_quote_lines');
        Schema::dropIfExists('tm_quote_versions');
        Schema::dropIfExists('tm_quotes');
        Schema::dropIfExists('tm_estimate_lines');
        Schema::dropIfExists('tm_estimate_versions');
        Schema::dropIfExists('tm_estimates');
    }
};

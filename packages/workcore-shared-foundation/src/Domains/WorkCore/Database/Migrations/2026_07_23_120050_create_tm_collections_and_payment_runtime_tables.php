<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tm_payment_evidence', function (Blueprint $table): void {
            $table->string('payment_method',32)->default('bank_transfer')->after('source_type');
        });
        Schema::create('tm_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id',64)->index();
            $table->uuid('payment_id')->index();
            $table->uuid('invoice_id')->index();
            $table->string('receipt_number',64);
            $table->string('state',32)->default('queued');
            $table->bigInteger('amount_minor');
            $table->char('currency_code',3);
            $table->string('document_id',128)->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('delivered_at')->nullable();
            $table->string('created_by_actor_type',32);
            $table->string('created_by_actor_id',128);
            $table->string('origin',32);
            $table->string('correlation_id',128);
            $table->timestamps();
            $table->unique(['company_id','receipt_number'],'tm_receipts_number_unique');
            $table->unique(['company_id','payment_id','invoice_id'],'tm_receipts_payment_invoice_unique');
        });

        Schema::create('tm_collection_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id',64)->index();
            $table->uuid('invoice_id')->index();
            $table->string('step_code',64);
            $table->string('channel',32);
            $table->string('tone',32);
            $table->string('state',32)->default('queued');
            $table->bigInteger('balance_minor');
            $table->char('currency_code',3);
            $table->string('external_message_id',255)->nullable();
            $table->string('actor_type',32);
            $table->string('actor_id',128);
            $table->string('origin',32);
            $table->string('correlation_id',128);
            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code',128)->nullable();
            $table->timestamps();
            $table->unique(['company_id','invoice_id','step_code','channel','correlation_id'],'tm_collection_messages_delivery_unique');
        });

        Schema::create('tm_collection_controls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id',64)->index();
            $table->uuid('invoice_id')->index();
            $table->boolean('paused')->default(false);
            $table->timestamp('paused_until')->nullable();
            $table->text('reason')->nullable();
            $table->string('updated_by_actor_type',32);
            $table->string('updated_by_actor_id',128);
            $table->timestamps();
            $table->unique(['company_id','invoice_id'],'tm_collection_controls_invoice_unique');
        });

        Schema::create('tm_payment_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id',64)->index();
            $table->uuid('invoice_id')->index();
            $table->string('state',32)->default('proposed');
            $table->bigInteger('total_minor');
            $table->char('currency_code',3);
            $table->json('instalments');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('created_by_actor_type',32);
            $table->string('created_by_actor_id',128);
            $table->timestamps();
            $table->index(['company_id','invoice_id','state'],'tm_payment_plans_invoice_state_index');
        });
    }

    public function down(): void
    {
        Schema::table('tm_payment_evidence', function (Blueprint $table): void {
            $table->dropColumn('payment_method');
        });
        Schema::dropIfExists('tm_payment_plans');
        Schema::dropIfExists('tm_collection_controls');
        Schema::dropIfExists('tm_collection_messages');
        Schema::dropIfExists('tm_receipts');
    }
};

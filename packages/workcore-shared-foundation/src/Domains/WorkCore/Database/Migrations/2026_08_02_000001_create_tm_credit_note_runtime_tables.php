<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tm_credit_note_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('credit_note_id')->index();
            $table->unsignedInteger('position');
            $table->string('description', 500);
            $table->bigInteger('amount_minor');
            $table->bigInteger('tax_minor')->default(0);
            $table->char('currency_code', 3);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['credit_note_id', 'position'], 'tm_credit_note_lines_position_unique');
        });
        Schema::create('tm_credit_note_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->uuid('credit_note_id')->index();
            $table->uuid('invoice_id')->index();
            $table->bigInteger('amount_minor');
            $table->char('currency_code', 3);
            $table->string('allocated_by_actor_type', 32);
            $table->string('allocated_by_actor_id', 128);
            $table->string('correlation_id', 128);
            $table->timestamp('allocated_at');
            $table->timestamps();
            $table->index(['company_id', 'invoice_id'], 'tm_credit_note_allocations_invoice_index');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tm_credit_note_allocations');
        Schema::dropIfExists('tm_credit_note_lines');
    }
};

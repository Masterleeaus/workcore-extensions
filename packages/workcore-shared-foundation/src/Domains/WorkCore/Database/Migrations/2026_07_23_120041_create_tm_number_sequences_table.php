<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tm_number_sequences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64);
            $table->string('sequence_key', 64);
            $table->string('period_key', 32)->default('global');
            $table->string('prefix', 32)->default('');
            $table->string('suffix', 32)->default('');
            $table->unsignedBigInteger('next_value')->default(1);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->enum('reset_policy', ['never', 'financial_year', 'calendar_year', 'monthly'])->default('never');
            $table->unsignedInteger('lock_version')->default(0);
            $table->string('created_by_actor_type', 32);
            $table->string('created_by_actor_id', 128);
            $table->string('updated_by_actor_type', 32);
            $table->string('updated_by_actor_id', 128);
            $table->string('correlation_id', 128)->nullable();
            $table->string('origin', 32)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'sequence_key', 'period_key'], 'tm_number_sequences_company_key_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_number_sequences');
    }
};

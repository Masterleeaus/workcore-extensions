<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tm_financial_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->unique();
            $table->char('base_currency_code', 3)->default('AUD');
            $table->string('timezone', 64)->default('Australia/Melbourne');
            $table->char('country_code', 2)->default('AU');
            $table->boolean('gst_registered')->default(false);
            $table->enum('gst_accounting_basis', ['cash', 'accrual'])->default('cash');
            $table->boolean('prices_include_tax')->default(true);
            $table->unsignedTinyInteger('financial_year_start_month')->default(7);
            $table->unsignedTinyInteger('financial_year_start_day')->default(1);
            $table->unsignedSmallInteger('default_payment_terms_days')->default(7);
            $table->string('rounding_mode', 32)->default('half_up');
            $table->unsignedInteger('settings_version')->default(1);
            $table->string('created_by_user_id', 64)->nullable();
            $table->string('updated_by_user_id', 64)->nullable();
            $table->string('created_by_actor_type', 32);
            $table->string('created_by_actor_id', 128);
            $table->string('updated_by_actor_type', 32);
            $table->string('updated_by_actor_id', 128);
            $table->string('correlation_id', 128)->nullable();
            $table->string('origin', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_financial_settings');
    }
};

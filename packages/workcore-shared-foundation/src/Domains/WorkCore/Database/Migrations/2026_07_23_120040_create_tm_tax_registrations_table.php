<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tm_tax_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('company_id', 64)->index();
            $table->string('jurisdiction_code', 32);
            $table->string('registration_type', 32);
            $table->string('registration_identifier', 128);
            $table->enum('status', ['pending', 'active', 'suspended', 'cancelled'])->default('active');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->json('settings')->nullable();
            $table->string('created_by_user_id', 64)->nullable();
            $table->string('updated_by_user_id', 64)->nullable();
            $table->string('created_by_actor_type', 32);
            $table->string('created_by_actor_id', 128);
            $table->string('updated_by_actor_type', 32);
            $table->string('updated_by_actor_id', 128);
            $table->string('correlation_id', 128)->nullable();
            $table->string('origin', 32)->nullable();
            $table->timestamps();
            $table->unique(
                ['company_id', 'jurisdiction_code', 'registration_type', 'registration_identifier'],
                'tm_tax_registrations_company_registration_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tm_tax_registrations');
    }
};

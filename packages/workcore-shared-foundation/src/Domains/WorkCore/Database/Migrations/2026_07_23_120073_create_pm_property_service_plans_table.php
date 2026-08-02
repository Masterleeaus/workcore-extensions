<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_property_service_plans')) {
            return;
        }
        Schema::create('pm_property_service_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('property_id')->index();
            $table->string('name');
            $table->string('service_type')->nullable(); // cleaning, inspection, maintenance, etc.
            $table->string('rrule')->nullable(); // RFC5545 RRULE
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->json('preferred_days')->nullable();
            $table->json('preferred_times')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_property_service_plans');
    }
};

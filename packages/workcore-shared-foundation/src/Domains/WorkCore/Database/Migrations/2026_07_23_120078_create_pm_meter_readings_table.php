<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_meter_readings')) {
            return;
        }

        Schema::create('pm_meter_readings', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('property_id')->index();
            $table->unsignedBigInteger('unit_id')->nullable()->index();

            $table->string('meter_type', 50)->default('water'); // water|electric|gas|other
            $table->date('reading_date')->index();

            $table->decimal('current_reading', 12, 2);
            $table->decimal('previous_reading', 12, 2)->nullable();

            $table->decimal('consumed', 12, 2)->default(0);
            $table->decimal('rate', 12, 4)->nullable();
            $table->decimal('amount', 12, 2)->nullable();

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();

            $table->timestamps();

            $table->index(['property_id', 'meter_type', 'reading_date'], 'pm_meter_readings_prop_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_meter_readings');
    }
};

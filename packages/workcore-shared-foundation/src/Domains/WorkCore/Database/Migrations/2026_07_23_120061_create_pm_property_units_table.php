<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pm_property_units')) {
            return;
        }
        Schema::create('pm_property_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('property_id')->index();

            $table->string('unit_code')->nullable()->index();
            $table->string('unit_name')->nullable();
            $table->string('floor')->nullable();
            $table->string('tower')->nullable();
            $table->string('type')->nullable();
            $table->decimal('area', 10, 2)->nullable();
            $table->string('address')->nullable();

            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('pm_properties')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_property_units');
    }
};

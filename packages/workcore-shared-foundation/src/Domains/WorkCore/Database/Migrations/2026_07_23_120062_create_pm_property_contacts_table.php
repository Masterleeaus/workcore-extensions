<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pm_property_contacts')) {
            return;
        }
        Schema::create('pm_property_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('property_id')->index();

            $table->string('role', 50)->default('contact')->index(); // owner | agent | tenant | cleaner | tradie | emergency
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('company')->nullable();
            $table->longText('notes')->nullable();

            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('pm_properties')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_property_contacts');
    }
};

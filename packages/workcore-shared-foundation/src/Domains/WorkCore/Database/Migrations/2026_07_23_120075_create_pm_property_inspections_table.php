<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_property_inspections')) {
            return;
        }
        Schema::create('pm_property_inspections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('property_id')->index();
            $table->string('inspection_type')->nullable(); // routine, exit, entry, safety, QA
            $table->dateTime('scheduled_for')->nullable();
            $table->unsignedBigInteger('inspected_by')->nullable()->index();
            $table->string('status')->default('scheduled'); // scheduled,in_progress,completed,cancelled
            $table->unsignedInteger('score')->nullable();
            $table->json('findings')->nullable();
            $table->json('actions')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_property_inspections');
    }
};

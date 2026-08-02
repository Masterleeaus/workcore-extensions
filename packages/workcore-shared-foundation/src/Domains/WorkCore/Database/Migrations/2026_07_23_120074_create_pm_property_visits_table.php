<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_property_visits')) {
            return;
        }
        Schema::create('pm_property_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('property_id')->index();
            $table->unsignedBigInteger('service_plan_id')->nullable()->index();
            $table->string('visit_type')->nullable(); // clean, inspection, maintenance, key handover
            $table->dateTime('scheduled_for')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->string('status')->default('scheduled'); // scheduled,in_progress,done,cancelled
            $table->text('notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_property_visits');
    }
};

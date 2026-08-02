<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_property_approvals')) {
            return;
        }
        Schema::create('pm_property_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('property_id')->index();
            $table->string('subject');
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->unsignedBigInteger('requested_to')->nullable()->index();
            $table->string('status')->default('pending'); // pending,approved,rejected,expired
            $table->json('request_payload')->nullable();
            $table->json('decision_payload')->nullable();
            $table->dateTime('requested_at')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_property_approvals');
    }
};

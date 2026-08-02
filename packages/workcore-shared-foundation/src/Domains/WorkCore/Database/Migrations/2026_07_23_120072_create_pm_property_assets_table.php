<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('pm_property_assets')) {
        return;
    }
    Schema::create('pm_property_assets', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->index();
      $table->unsignedBigInteger('property_id')->index();
      $table->string('label', 190);
      $table->string('category', 80)->nullable();
      $table->string('serial', 120)->nullable();
      $table->string('location', 190)->nullable();
      $table->text('notes')->nullable();
      $table->timestamps();
      $table->index(['company_id','property_id']);
    });
  }
  public function down(): void { Schema::dropIfExists('pm_property_assets'); }
};

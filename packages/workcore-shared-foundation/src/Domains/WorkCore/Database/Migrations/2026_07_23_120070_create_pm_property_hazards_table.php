<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('pm_property_hazards')) {
        return;
    }
    Schema::create('pm_property_hazards', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('user_id')->index();
      $table->unsignedBigInteger('property_id')->index();
      $table->string('hazard', 190);
      $table->string('risk_level', 30)->default('medium');
      $table->text('controls')->nullable();
      $table->timestamps();
      $table->index(['company_id','property_id']);
    });
  }
  public function down(): void { Schema::dropIfExists('pm_property_hazards'); }
};

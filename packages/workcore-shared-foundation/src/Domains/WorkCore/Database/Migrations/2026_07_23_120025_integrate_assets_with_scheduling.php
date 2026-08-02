<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_appointment_assets', function (Blueprint $table): void {
                $table->id();
                $table->char('public_id', 26)->unique();
                $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('appointment_id')->constrained('tz_appointments')->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
                $table->string('role', 50)->default('required');
                $table->boolean('is_required')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['appointment_id', 'asset_id'], 'tz_appointment_assets_unique');
                $table->index(['company_id', 'asset_id'], 'tz_appointment_assets_company_asset');
                $table->index(['company_id', 'appointment_id'], 'tz_appointment_assets_company_appointment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_appointment_assets');
    }
};

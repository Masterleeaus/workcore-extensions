<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_companies', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->string('timezone')->default('Australia/Melbourne');
            $table->char('currency_code', 3)->default('AUD');
            $table->char('country_code', 2)->default('AU');
            $table->string('abn', 20)->nullable();
            $table->boolean('gst_registered')->default(false);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'owner_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_companies');
    }
};

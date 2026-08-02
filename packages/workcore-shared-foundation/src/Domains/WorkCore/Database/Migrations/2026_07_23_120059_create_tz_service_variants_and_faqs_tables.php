<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_service_variants', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('tz_services')->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('default_duration_minutes')->nullable();
            $table->string('pricing_model', 30)->default('fixed');
            $table->decimal('base_price', 14, 2)->nullable();
            $table->string('status', 30)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'service_id', 'key'], 'tz_service_variant_key_unique');
            $table->index(['company_id', 'service_id', 'status', 'sort_order'], 'tz_service_variant_lookup_idx');
        });

        Schema::create('tz_service_faqs', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('tz_services')->cascadeOnDelete();
            $table->text('question');
            $table->longText('answer');
            $table->string('status', 30)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'service_id', 'status', 'sort_order'], 'tz_service_faq_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_service_faqs');
        Schema::dropIfExists('tz_service_variants');
    }
};

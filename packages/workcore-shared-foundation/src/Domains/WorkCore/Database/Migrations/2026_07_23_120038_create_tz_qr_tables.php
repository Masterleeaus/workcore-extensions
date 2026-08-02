<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_qr_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained('tz_companies')->cascadeOnDelete();
            $table->unsignedInteger('default_size')->default(300);
            $table->unsignedInteger('default_margin')->default(2);
            $table->string('foreground_color', 9)->default('#000000');
            $table->string('background_color', 9)->default('#ffffff');
            $table->boolean('dynamic_codes_enabled')->default(true);
            $table->json('allowed_types')->nullable();
            $table->timestamps();
        });

        Schema::create('tz_qr_codes', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('title');
            $table->string('purpose', 80)->nullable();
            $table->string('type', 30)->default('text');
            $table->longText('payload_ciphertext');
            $table->char('payload_hash', 64);
            $table->string('target_type', 80)->nullable();
            $table->char('target_public_id', 26)->nullable();
            $table->boolean('is_dynamic')->default(false);
            $table->string('access_mode', 20)->default('internal');
            $table->longText('public_token_ciphertext')->nullable();
            $table->char('public_token_hash', 64)->nullable()->unique();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('size')->default(300);
            $table->unsignedInteger('margin')->default(2);
            $table->string('foreground_color', 9)->default('#000000');
            $table->string('background_color', 9)->default('#ffffff');
            $table->string('logo_path', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_scans')->nullable();
            $table->unsignedInteger('scan_count')->default(0);
            $table->timestamp('last_scanned_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status', 'type']);
            $table->index(['company_id', 'target_type', 'target_public_id']);
            $table->index(['company_id', 'expires_at']);
            $table->index(['company_id', 'purpose']);
        });

        Schema::create('tz_qr_scans', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('qr_code_id')->constrained('tz_qr_codes')->cascadeOnDelete();
            $table->string('outcome', 30)->default('resolved');
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamp('created_at')->nullable();
            $table->index(['company_id', 'qr_code_id', 'scanned_at']);
            $table->index(['company_id', 'outcome', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_qr_scans');
        Schema::dropIfExists('tz_qr_codes');
        Schema::dropIfExists('tz_qr_settings');
    }
};

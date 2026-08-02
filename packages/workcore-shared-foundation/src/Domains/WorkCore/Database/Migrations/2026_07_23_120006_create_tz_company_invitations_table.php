<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_company_invitations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('tz_company_roles')->nullOnDelete();
            $table->string('email');
            $table->string('role_key')->default('member');
            $table->char('token_hash', 64)->unique();
            $table->string('status')->default('pending');
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'email', 'status'], 'tz_company_invitation_state_unique');
            $table->index(['email', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_company_invitations');
    }
};

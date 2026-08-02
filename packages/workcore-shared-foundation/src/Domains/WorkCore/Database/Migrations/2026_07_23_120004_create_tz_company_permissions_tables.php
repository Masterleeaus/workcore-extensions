<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_company_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('tz_company_roles')->cascadeOnDelete();
            $table->string('permission');
            $table->string('access_level')->default('none');
            $table->timestamps();
            $table->unique(['role_id', 'permission']);
            $table->index(['company_id', 'permission']);
        });

        Schema::create('tz_company_member_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('tz_company_memberships')->cascadeOnDelete();
            $table->string('permission');
            $table->string('access_level')->default('none');
            $table->timestamps();
            $table->unique(['membership_id', 'permission']);
            $table->index(['company_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_company_member_permissions');
        Schema::dropIfExists('tz_company_role_permissions');
    }
};

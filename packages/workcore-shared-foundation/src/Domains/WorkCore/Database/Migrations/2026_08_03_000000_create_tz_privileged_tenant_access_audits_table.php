<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tz_privileged_tenant_access_audits')) {
            return;
        }

        Schema::create('tz_privileged_tenant_access_audits', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('public_id', 26)->unique();
            $table->string('actor_id', 190)->index();
            $table->string('reason', 1000);
            $table->string('status', 20)->index();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_privileged_tenant_access_audits');
    }
};

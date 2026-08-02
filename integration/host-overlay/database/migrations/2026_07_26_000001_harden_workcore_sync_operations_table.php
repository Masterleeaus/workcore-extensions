<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workcore_sync_operations', function (Blueprint $table): void {
            $table->char('payload_sha256', 64)->nullable()->after('payload_json');
            $table->unsignedInteger('attempts')->default(0)->after('status');
            $table->timestamp('available_at')->nullable()->after('attempts');
            $table->timestamp('locked_at')->nullable()->after('available_at');
            $table->index(['status', 'available_at'], 'workcore_sync_ready_idx');
        });
    }

    public function down(): void
    {
        Schema::table('workcore_sync_operations', function (Blueprint $table): void {
            $table->dropIndex('workcore_sync_ready_idx');
            $table->dropColumn(['payload_sha256', 'attempts', 'available_at', 'locked_at']);
        });
    }
};

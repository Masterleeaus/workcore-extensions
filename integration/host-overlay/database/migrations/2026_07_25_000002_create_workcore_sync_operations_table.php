<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workcore_sync_operations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->uuid('operation_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('device_id', 190);
            $table->string('action_key', 190);
            $table->longText('payload_json');
            $table->string('status', 32)->default('pending');
            $table->longText('result_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'operation_id']);
            $table->index(['company_id', 'status', 'updated_at']);
            $table->foreign('actor_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workcore_sync_operations');
    }
};

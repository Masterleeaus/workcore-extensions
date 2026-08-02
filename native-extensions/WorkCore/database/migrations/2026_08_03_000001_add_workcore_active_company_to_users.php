<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'active_company_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('active_company_id')->nullable()->index();
        });

        if (DB::getDriverName() !== 'sqlite' && Schema::hasTable('tz_companies')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreign('active_company_id')
                    ->references('id')
                    ->on('tz_companies')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'active_company_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['active_company_id']);
            }
            $table->dropColumn('active_company_id');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'active_company_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('active_company_id')->nullable()->after('id')->constrained('tz_companies')->nullOnDelete();
            });
        }

        if (Schema::hasTable('conversations') && ! Schema::hasColumn('conversations', 'company_id')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('tz_companies')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
                $table->string('visibility')->default('company');
                $table->softDeletes();
                $table->index(['company_id', 'type']);
            });
        }

        if (Schema::hasTable('participants') && ! Schema::hasColumn('participants', 'company_id')) {
            Schema::table('participants', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('tz_companies')->cascadeOnDelete();
                $table->index(['company_id', 'user_id']);
            });
        }

        if (Schema::hasTable('messages') && ! Schema::hasColumn('messages', 'company_id')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('tz_companies')->cascadeOnDelete();
                $table->uuid('public_id')->nullable()->unique()->after('company_id');
                $table->char('attachment_sha256', 64)->nullable();
                $table->string('attachment_disk')->nullable();
                $table->json('metadata')->nullable();
                $table->softDeletes();
                $table->index(['company_id', 'conversation_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'company_id')) {
            Schema::table('messages', function (Blueprint $table): void {
                $table->dropSoftDeletes();
                $table->dropColumn(['company_id', 'public_id', 'attachment_sha256', 'attachment_disk', 'metadata']);
            });
        }

        if (Schema::hasTable('participants') && Schema::hasColumn('participants', 'company_id')) {
            Schema::table('participants', fn (Blueprint $table) => $table->dropColumn('company_id'));
        }

        if (Schema::hasTable('conversations') && Schema::hasColumn('conversations', 'company_id')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->dropSoftDeletes();
                $table->dropColumn(['company_id', 'created_by', 'visibility']);
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'active_company_id')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('active_company_id'));
        }
    }
};

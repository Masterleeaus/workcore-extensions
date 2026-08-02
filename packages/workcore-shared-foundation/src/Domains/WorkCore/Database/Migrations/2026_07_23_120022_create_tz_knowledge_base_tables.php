<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_kb_categories', function (Blueprint $table): void {
            $table->id();$table->char('public_id',26)->unique();$table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tz_kb_categories')->nullOnDelete();$table->string('name',160);$table->string('slug',180);$table->text('description')->nullable();
            $table->string('visibility',30)->default('internal');$table->unsignedInteger('sort_order')->default(0);$table->boolean('active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();$table->timestamps();$table->softDeletes();$table->unique(['company_id','slug']);
        });
        Schema::create('tz_kb_articles', function (Blueprint $table): void {
            $table->id();$table->char('public_id',26)->unique();$table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('tz_kb_categories')->nullOnDelete();$table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('tz_assets')->nullOnDelete();$table->foreignId('source_support_ticket_id')->nullable()->constrained('tz_support_tickets')->nullOnDelete();
            $table->string('slug',180);$table->string('title');$table->text('summary')->nullable();$table->longText('body');$table->string('article_type',50)->default('procedure');
            $table->string('visibility',30)->default('internal');$table->json('audiences')->nullable();$table->text('keywords')->nullable();$table->string('status',30)->default('draft');
            $table->unsignedInteger('current_version')->default(1);$table->boolean('pinned')->default(false);$table->boolean('requires_acknowledgement')->default(false);
            $table->date('review_due_on')->nullable();$table->timestamp('publish_at')->nullable();$table->timestamp('expires_at')->nullable();$table->timestamp('published_at')->nullable();$table->timestamp('retired_at')->nullable();
            $table->json('metadata')->nullable();$table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();$table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();$table->timestamps();$table->softDeletes();
            $table->unique(['company_id','slug']);$table->index(['company_id','status','visibility']);$table->index(['company_id','article_type','status']);$table->index(['company_id','review_due_on']);
        });
        Schema::create('tz_kb_article_versions', function (Blueprint $table): void {
            $table->id();$table->char('public_id',26)->unique();$table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();$table->foreignId('knowledge_article_id')->constrained('tz_kb_articles')->cascadeOnDelete();
            $table->unsignedInteger('version_number');$table->string('title');$table->text('summary')->nullable();$table->longText('body');$table->string('article_type',50);$table->string('visibility',30);$table->json('audiences')->nullable();$table->text('keywords')->nullable();$table->string('change_summary',500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();$table->timestamp('created_at')->nullable();$table->unique(['knowledge_article_id','version_number']);
        });
        Schema::create('tz_kb_tags', function (Blueprint $table): void {
            $table->id();$table->char('public_id',26)->unique();$table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();$table->string('name',80);$table->string('slug',100);$table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();$table->timestamps();$table->unique(['company_id','slug']);
        });
        Schema::create('tz_kb_article_tags', function (Blueprint $table): void {
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();$table->foreignId('knowledge_article_id')->constrained('tz_kb_articles')->cascadeOnDelete();$table->foreignId('knowledge_tag_id')->constrained('tz_kb_tags')->cascadeOnDelete();$table->timestamp('created_at')->nullable();$table->primary(['knowledge_article_id','knowledge_tag_id']);
        });
        Schema::create('tz_kb_article_feedback', function (Blueprint $table): void {
            $table->id();$table->char('public_id',26)->unique();$table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();$table->foreignId('knowledge_article_id')->constrained('tz_kb_articles')->cascadeOnDelete();$table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();$table->string('rating',20);$table->text('comment')->nullable();$table->json('context')->nullable();$table->timestamp('created_at')->nullable();$table->index(['company_id','knowledge_article_id','rating']);
        });
        Schema::create('tz_kb_article_status_history', function (Blueprint $table): void {
            $table->id();$table->char('public_id',26)->unique();$table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();$table->foreignId('knowledge_article_id')->constrained('tz_kb_articles')->cascadeOnDelete();$table->string('from_status',30)->nullable();$table->string('to_status',30);$table->text('reason')->nullable();$table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();$table->timestamp('changed_at');$table->timestamp('created_at')->nullable();$table->index(['company_id','knowledge_article_id','changed_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tz_kb_article_status_history');Schema::dropIfExists('tz_kb_article_feedback');Schema::dropIfExists('tz_kb_article_tags');Schema::dropIfExists('tz_kb_tags');Schema::dropIfExists('tz_kb_article_versions');Schema::dropIfExists('tz_kb_articles');Schema::dropIfExists('tz_kb_categories');
    }
};

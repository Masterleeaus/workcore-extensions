<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const DEFAULT_STAGES = [
        ['key' => 'new', 'name' => 'New', 'color' => '#64748b', 'probability' => 10, 'position' => 0, 'is_closed' => false, 'is_won' => false],
        ['key' => 'qualified', 'name' => 'Qualified', 'color' => '#2563eb', 'probability' => 30, 'position' => 1, 'is_closed' => false, 'is_won' => false],
        ['key' => 'proposal', 'name' => 'Proposal', 'color' => '#9333ea', 'probability' => 55, 'position' => 2, 'is_closed' => false, 'is_won' => false],
        ['key' => 'negotiation', 'name' => 'Negotiation', 'color' => '#d97706', 'probability' => 80, 'position' => 3, 'is_closed' => false, 'is_won' => false],
        ['key' => 'won', 'name' => 'Won', 'color' => '#16a34a', 'probability' => 100, 'position' => 4, 'is_closed' => true, 'is_won' => true],
        ['key' => 'lost', 'name' => 'Lost', 'color' => '#dc2626', 'probability' => 0, 'position' => 5, 'is_closed' => true, 'is_won' => false],
    ];

    public function up(): void
    {
        Schema::create('tz_sales_pipelines', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('name', 160);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'key'], 'tz_sales_pipelines_company_key_unique');
            $table->index(['company_id', 'is_default', 'is_active'], 'tz_sales_pipelines_company_default_idx');
        });

        Schema::create('tz_sales_pipeline_stages', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained('tz_sales_pipelines')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('name', 160);
            $table->string('color', 9)->default('#2563eb');
            $table->unsignedInteger('position')->default(0);
            $table->unsignedTinyInteger('probability')->default(0);
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_won')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['pipeline_id', 'key'], 'tz_sales_pipeline_stages_pipeline_key_unique');
            $table->index(['company_id', 'pipeline_id', 'position'], 'tz_sales_pipeline_stages_order_idx');
        });

        Schema::create('tz_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained('tz_sales_pipelines')->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('tz_sales_pipeline_stages')->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('tz_leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('tz_customers')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('tz_customer_contacts')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->char('currency_code', 3)->default('AUD');
            $table->string('status', 24)->default('open');
            $table->unsignedTinyInteger('probability')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->date('expected_close_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('lost_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status', 'expected_close_date'], 'tz_opportunities_status_close_idx');
            $table->index(['company_id', 'pipeline_id', 'stage_id', 'position'], 'tz_opportunities_board_idx');
            $table->index(['company_id', 'owner_user_id'], 'tz_opportunities_owner_idx');
            $table->index(['company_id', 'customer_id'], 'tz_opportunities_customer_idx');
        });

        Schema::create('tz_crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('subject_type', 40);
            $table->char('subject_public_id', 26);
            $table->string('type', 32)->default('note');
            $table->string('title', 255)->nullable();
            $table->text('body')->nullable();
            $table->string('direction', 16)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'subject_type', 'subject_public_id', 'created_at'], 'tz_crm_activities_subject_idx');
            $table->index(['company_id', 'type', 'created_at'], 'tz_crm_activities_type_idx');
        });

        DB::table('tz_companies')->select('id')->orderBy('id')->each(function (object $company): void {
            $now = now();
            $pipelineId = DB::table('tz_sales_pipelines')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'company_id' => (int) $company->id,
                'key' => 'sales',
                'name' => 'Sales Pipeline',
                'is_default' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (self::DEFAULT_STAGES as $stage) {
                DB::table('tz_sales_pipeline_stages')->insert([
                    'public_id' => (string) Str::ulid(),
                    'company_id' => (int) $company->id,
                    'pipeline_id' => $pipelineId,
                    'key' => $stage['key'],
                    'name' => $stage['name'],
                    'color' => $stage['color'],
                    'probability' => $stage['probability'],
                    'position' => $stage['position'],
                    'is_closed' => $stage['is_closed'],
                    'is_won' => $stage['is_won'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_crm_activities');
        Schema::dropIfExists('tz_opportunities');
        Schema::dropIfExists('tz_sales_pipeline_stages');
        Schema::dropIfExists('tz_sales_pipelines');
    }
};

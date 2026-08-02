<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_workers', function (Blueprint $table): void {
            $table->id(); $table->char('public_id',26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('worker_number',100); $table->string('worker_type',50)->default('employee');
            $table->string('display_name',200); $table->string('job_title',150)->nullable();
            $table->string('employment_status',40)->default('active'); $table->string('timezone',100)->default('Australia/Melbourne');
            $table->string('phone',50)->nullable(); $table->string('email',200)->nullable();
            $table->decimal('hourly_cost',12,2)->nullable(); $table->decimal('hourly_charge_rate',12,2)->nullable();
            $table->json('service_areas')->nullable(); $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(); $table->softDeletes();
            $table->unique(['company_id','user_id']); $table->unique(['company_id','worker_number']);
            $table->index(['company_id','employment_status','worker_type']);
        });
        Schema::create('tz_skills', function (Blueprint $table): void {
            $table->id(); $table->char('public_id',26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('name',150); $table->string('code',100); $table->text('description')->nullable();
            $table->string('category',100)->nullable(); $table->boolean('requires_certification')->default(false); $table->boolean('active')->default(true);
            $table->timestamps(); $table->softDeletes(); $table->unique(['company_id','code']); $table->index(['company_id','active','category']);
        });
        Schema::create('tz_worker_skills', function (Blueprint $table): void {
            $table->id(); $table->char('public_id',26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete(); $table->foreignId('skill_id')->constrained('tz_skills')->cascadeOnDelete();
            $table->string('proficiency',30)->default('competent'); $table->unsignedInteger('years_experience')->nullable();
            $table->boolean('verified')->default(false); $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable(); $table->timestamps(); $table->unique(['worker_id','skill_id']); $table->index(['company_id','skill_id','verified']);
        });
        Schema::create('tz_worker_certifications', function (Blueprint $table): void {
            $table->id(); $table->char('public_id',26)->unique(); $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete(); $table->foreignId('skill_id')->nullable()->constrained('tz_skills')->nullOnDelete();
            $table->string('name',200); $table->string('issuer',200)->nullable(); $table->string('credential_number',150)->nullable();
            $table->date('issued_on')->nullable(); $table->date('expires_on')->nullable(); $table->string('status',30)->default('valid');
            $table->string('document_path',500)->nullable(); $table->text('notes')->nullable(); $table->timestamps(); $table->softDeletes();
            $table->index(['company_id','worker_id','status']); $table->index(['company_id','expires_on']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tz_worker_certifications'); Schema::dropIfExists('tz_worker_skills'); Schema::dropIfExists('tz_skills'); Schema::dropIfExists('tz_workers');
    }
};

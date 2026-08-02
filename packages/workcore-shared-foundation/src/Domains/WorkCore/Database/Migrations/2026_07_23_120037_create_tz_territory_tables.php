<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_regions', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('manager_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->string('code', 100);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('timezone', 100)->default('Australia/Melbourne');
            $table->string('status', 30)->default('active');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(); $table->softDeletes();
            $table->unique(['company_id','code']);
            $table->index(['company_id','status','name']);
        });

        Schema::create('tz_districts', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('tz_regions')->cascadeOnDelete();
            $table->foreignId('manager_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->string('code', 100); $table->string('name', 200); $table->text('description')->nullable();
            $table->string('status', 30)->default('active'); $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(); $table->softDeletes();
            $table->unique(['company_id','code']); $table->index(['company_id','region_id','status']);
        });

        Schema::create('tz_branches', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('district_id')->constrained('tz_districts')->cascadeOnDelete();
            $table->foreignId('manager_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained('tz_premises')->nullOnDelete();
            $table->string('code', 100); $table->string('name', 200); $table->text('description')->nullable();
            $table->string('phone', 50)->nullable(); $table->string('email', 200)->nullable();
            $table->string('status', 30)->default('active'); $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(); $table->softDeletes();
            $table->unique(['company_id','code']); $table->index(['company_id','district_id','status']);
        });

        Schema::create('tz_territories', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('tz_branches')->cascadeOnDelete();
            $table->string('code', 100); $table->string('name', 200); $table->text('description')->nullable();
            $table->string('territory_type', 40)->default('postcode');
            $table->json('postcode_patterns')->nullable(); $table->json('suburbs')->nullable();
            $table->string('state', 100)->nullable(); $table->char('country_code', 2)->default('AU');
            $table->decimal('centre_latitude', 10, 7)->nullable(); $table->decimal('centre_longitude', 10, 7)->nullable();
            $table->decimal('radius_km', 10, 2)->nullable(); $table->longText('polygon_geojson')->nullable();
            $table->string('status', 30)->default('active'); $table->unsignedInteger('priority')->default(100);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(); $table->softDeletes();
            $table->unique(['company_id','code']);
            $table->index(['company_id','branch_id','status']); $table->index(['company_id','priority']);
        });

        Schema::create('tz_territory_assignments', function (Blueprint $table): void {
            $table->id(); $table->char('public_id',26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('territory_id')->constrained('tz_territories')->cascadeOnDelete();
            $table->string('assignable_type', 50); $table->char('assignable_public_id',26);
            $table->string('assignment_role', 50)->default('coverage'); $table->unsignedInteger('priority')->default(100);
            $table->date('effective_from')->nullable(); $table->date('effective_until')->nullable();
            $table->boolean('active')->default(true); $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id','territory_id','assignable_type','assignable_public_id','assignment_role'], 'tz_territory_assignment_unique');
            $table->index(['company_id','assignable_type','assignable_public_id','active'], 'tz_territory_assignable_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_territory_assignments');
        Schema::dropIfExists('tz_territories');
        Schema::dropIfExists('tz_branches');
        Schema::dropIfExists('tz_districts');
        Schema::dropIfExists('tz_regions');
    }
};

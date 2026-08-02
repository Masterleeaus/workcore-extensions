<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $scopedTables = [
        'tz_service_categories', 'tz_services', 'tz_job_templates', 'tz_work_orders',
        'tz_appointments', 'tz_dispatch_assignments', 'tz_form_templates', 'tz_assets',
        'tz_inventory_items',
    ];

    public function up(): void
    {
        foreach ($this->scopedTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('business_line_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('tz_business_lines')
                    ->nullOnDelete();
                $table->index(['company_id', 'business_line_id'], $table->getTable() . '_business_line_idx');
            });
        }

        Schema::table('tz_task_templates', function (Blueprint $table): void {
            $table->boolean('active')->default(true)->after('is_required');
        });

        Schema::create('tz_worker_business_lines', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->foreignId('business_line_id')->constrained('tz_business_lines')->cascadeOnDelete();
            $table->string('role', 80)->default('worker');
            $table->boolean('is_primary')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['worker_id', 'business_line_id'], 'tz_worker_business_line_unique');
            $table->index(['company_id', 'business_line_id', 'active'], 'tz_worker_business_line_lookup_idx');
        });

        Schema::create('tz_trade_licences', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('tz_workers')->cascadeOnDelete();
            $table->foreignId('business_line_id')->nullable()->constrained('tz_business_lines')->nullOnDelete();
            $table->string('vertical_key', 100);
            $table->string('licence_type', 100);
            $table->string('licence_class', 120)->nullable();
            $table->string('licence_number', 160);
            $table->string('jurisdiction', 80)->default('AU-VIC');
            $table->string('issuer', 200)->nullable();
            $table->string('status', 30)->default('valid');
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('conditions')->nullable();
            $table->string('document_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'licence_number', 'jurisdiction'], 'tz_trade_licence_number_unique');
            $table->index(['company_id', 'vertical_key', 'status'], 'tz_trade_licence_status_idx');
            $table->index(['company_id', 'worker_id', 'expires_on'], 'tz_trade_licence_expiry_idx');
        });

        Schema::create('tz_work_order_certificates', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('tz_work_orders')->cascadeOnDelete();
            $table->foreignId('business_line_id')->nullable()->constrained('tz_business_lines')->nullOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->foreignId('trade_licence_id')->nullable()->constrained('tz_trade_licences')->nullOnDelete();
            $table->string('vertical_key', 100);
            $table->string('certificate_type', 100);
            $table->string('certificate_number', 160);
            $table->string('status', 30)->default('issued');
            $table->timestamp('issued_at');
            $table->json('test_results')->nullable();
            $table->json('declaration')->nullable();
            $table->string('document_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'certificate_number'], 'tz_work_order_certificate_number_unique');
            $table->index(['company_id', 'work_order_id', 'status'], 'tz_work_order_certificate_work_idx');
            $table->index(['company_id', 'vertical_key', 'issued_at'], 'tz_work_order_certificate_trade_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tz_work_order_certificates');
        Schema::dropIfExists('tz_trade_licences');
        Schema::dropIfExists('tz_worker_business_lines');

        Schema::table('tz_task_templates', function (Blueprint $table): void {
            $table->dropColumn('active');
        });

        foreach (array_reverse($this->scopedTables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['business_line_id']);
                $table->dropIndex($table->getTable() . '_business_line_idx');
                $table->dropColumn('business_line_id');
            });
        }
    }
};

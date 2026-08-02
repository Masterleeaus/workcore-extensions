<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->expandCategories();
        $this->createMigrationConflicts();
        $this->expandAssets();
        $this->normaliseLegacySerials();
        $this->addAssetIndexes();
        $this->createIdentifiers();
        $this->createConditionRecords();
        $this->expandAssignments();
        $this->createDamageReports();
        $this->createMaintenancePlans();
        $this->expandMaintenanceRecords();
        $this->expandMeterReadings();
        $this->createCalibrations();
        $this->createWarranties();
        $this->createWarrantyClaims();
        $this->createDocumentLinks();
        $this->createSupplyHandoffs();
        $this->expandStatusHistory();
        $this->backfillCustodyLock();
    }

    private function expandCategories(): void
    {
        Schema::table('tz_asset_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('tz_asset_categories', 'requires_maintenance')) {
                $table->boolean('requires_maintenance')->default(false)->after('requires_service_schedule');
            }
            if (! Schema::hasColumn('tz_asset_categories', 'requires_calibration')) {
                $table->boolean('requires_calibration')->default(false)->after('requires_maintenance');
            }
            if (! Schema::hasColumn('tz_asset_categories', 'settings')) {
                $table->json('settings')->nullable()->after('requires_calibration');
            }
        });
    }

    private function expandAssets(): void
    {
        Schema::table('tz_assets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tz_assets', 'premises_zone_public_id')) {
                $table->char('premises_zone_public_id', 26)->nullable()->after('premises_id');
            }
            if (! Schema::hasColumn('tz_assets', 'storage_location_id')) {
                $table->foreignId('storage_location_id')->nullable()->after('premises_zone_public_id')->constrained('tz_stock_locations')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_assets', 'current_team_public_id')) {
                $table->char('current_team_public_id', 26)->nullable()->after('custodian_worker_id');
            }
            if (! Schema::hasColumn('tz_assets', 'current_vehicle_public_id')) {
                $table->char('current_vehicle_public_id', 26)->nullable()->after('current_team_public_id');
            }
            if (! Schema::hasColumn('tz_assets', 'current_job_id')) {
                $table->foreignId('current_job_id')->nullable()->after('current_vehicle_public_id')->constrained('tz_work_orders')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_assets', 'supply_item_public_id')) {
                $table->char('supply_item_public_id', 26)->nullable()->after('purchase_cost');
            }
            if (! Schema::hasColumn('tz_assets', 'supply_goods_receipt_item_public_id')) {
                $table->char('supply_goods_receipt_item_public_id', 26)->nullable()->after('supply_item_public_id');
            }
            if (! Schema::hasColumn('tz_assets', 'supply_purchase_order_public_id')) {
                $table->char('supply_purchase_order_public_id', 26)->nullable()->after('supply_goods_receipt_item_public_id');
            }
            if (! Schema::hasColumn('tz_assets', 'supplier_public_id')) {
                $table->char('supplier_public_id', 26)->nullable()->after('supply_purchase_order_public_id');
            }
            if (! Schema::hasColumn('tz_assets', 'currency')) {
                $table->char('currency', 3)->nullable()->after('purchase_cost');
            }
            if (! Schema::hasColumn('tz_assets', 'warranty_starts_on')) {
                $table->date('warranty_starts_on')->nullable()->after('purchase_cost');
            }
            if (! Schema::hasColumn('tz_assets', 'next_calibration_due_on')) {
                $table->date('next_calibration_due_on')->nullable()->after('next_inspection_due_on');
            }
            if (! Schema::hasColumn('tz_assets', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('next_calibration_due_on');
            }
            if (! Schema::hasColumn('tz_assets', 'lock_version')) {
                $table->unsignedInteger('lock_version')->default(0)->after('last_seen_at');
            }
            if (! Schema::hasColumn('tz_assets', 'retired_at')) {
                $table->timestamp('retired_at')->nullable()->after('lock_version');
            }
            if (! Schema::hasColumn('tz_assets', 'disposed_at')) {
                $table->timestamp('disposed_at')->nullable()->after('retired_at');
            }
            if (! Schema::hasColumn('tz_assets', 'written_off_at')) {
                $table->timestamp('written_off_at')->nullable()->after('disposed_at');
            }
        });

    }

    private function createMigrationConflicts(): void
    {
        Schema::create('tz_asset_migration_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->string('field', 80);
            $table->string('original_value', 255)->nullable();
            $table->string('conflict_type', 80);
            $table->string('resolution_status', 32)->default('pending');
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'resolution_status'], 'tz_asset_merge_conflict_status_idx');
        });
    }

    private function normaliseLegacySerials(): void
    {
        DB::table('tz_assets')->whereNotNull('serial_number')->whereRaw("TRIM(serial_number) = ''")->update(['serial_number' => null]);
        $duplicates = DB::table('tz_assets')
            ->select(['company_id', 'serial_number'])
            ->whereNotNull('serial_number')
            ->groupBy(['company_id', 'serial_number'])
            ->havingRaw('COUNT(*) > 1')
            ->get();
        foreach ($duplicates as $duplicate) {
            $assets = DB::table('tz_assets')
                ->where('company_id', $duplicate->company_id)
                ->where('serial_number', $duplicate->serial_number)
                ->orderBy('id')
                ->get();
            foreach ($assets->slice(1) as $asset) {
                DB::table('tz_asset_migration_conflicts')->insert([
                    'public_id' => (string) \Illuminate\Support\Str::ulid(),
                    'company_id' => $asset->company_id,
                    'asset_id' => $asset->id,
                    'field' => 'serial_number',
                    'original_value' => $asset->serial_number,
                    'conflict_type' => 'duplicate_within_company',
                    'resolution_status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('tz_assets')->where('id', $asset->id)->update(['serial_number' => null, 'updated_at' => now()]);
            }
        }
    }

    private function addAssetIndexes(): void
    {
        Schema::table('tz_assets', function (Blueprint $table): void {
            $table->unique(['company_id', 'serial_number'], 'tz_asset_company_serial_unique');
            $table->index(['company_id', 'supply_item_public_id'], 'tz_asset_company_supply_item_idx');
            $table->index(['company_id', 'next_calibration_due_on'], 'tz_asset_company_calibration_due_idx');
        });
    }

    private function createIdentifiers(): void
    {
        Schema::create('tz_asset_identifiers', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->string('identifier_type', 32);
            $table->string('value', 255);
            $table->boolean('is_primary')->default(false);
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'identifier_type', 'value'], 'tz_asset_identifier_lookup_unique');
            $table->index(['company_id', 'asset_id', 'active'], 'tz_asset_identifier_asset_idx');
        });
    }

    private function createConditionRecords(): void
    {
        Schema::create('tz_asset_condition_records', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('tz_asset_assignments')->nullOnDelete();
            $table->string('context', 40)->default('inspection');
            $table->string('grade', 32);
            $table->text('summary')->nullable();
            $table->json('measurements')->nullable();
            $table->json('evidence_file_references')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['company_id', 'asset_id', 'recorded_at'], 'tz_asset_condition_history_idx');
        });
    }

    private function expandAssignments(): void
    {
        Schema::table('tz_asset_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('tz_asset_assignments', 'allocation_mode')) {
                $table->string('allocation_mode', 32)->default('assignment')->after('assignment_type');
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'team_public_id')) {
                $table->char('team_public_id', 26)->nullable()->after('worker_id');
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'vehicle_public_id')) {
                $table->char('vehicle_public_id', 26)->nullable()->after('team_public_id');
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'job_id')) {
                $table->foreignId('job_id')->nullable()->after('vehicle_public_id')->constrained('tz_work_orders')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'storage_location_id')) {
                $table->foreignId('storage_location_id')->nullable()->after('job_id')->constrained('tz_stock_locations')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'premises_zone_public_id')) {
                $table->char('premises_zone_public_id', 26)->nullable()->after('premises_id');
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'custodian_user_id')) {
                $table->foreignId('custodian_user_id')->nullable()->after('assigned_by_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'issued_by_user_id')) {
                $table->foreignId('issued_by_user_id')->nullable()->after('custodian_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'returned_by_user_id')) {
                $table->foreignId('returned_by_user_id')->nullable()->after('issued_by_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'expected_return_at')) {
                $table->timestamp('expected_return_at')->nullable()->after('ends_at');
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'returned_at')) {
                $table->timestamp('returned_at')->nullable()->after('expected_return_at');
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'condition_out_id')) {
                $table->foreignId('condition_out_id')->nullable()->after('returned_at')->constrained('tz_asset_condition_records')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'condition_in_id')) {
                $table->foreignId('condition_in_id')->nullable()->after('condition_out_id')->constrained('tz_asset_condition_records')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'acknowledged_by_user_id')) {
                $table->foreignId('acknowledged_by_user_id')->nullable()->after('condition_in_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable()->after('acknowledged_by_user_id');
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'status')) {
                $table->string('status', 32)->default('active')->after('active');
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'active_lock')) {
                $table->string('active_lock', 16)->nullable()->after('status');
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'previous_assignment_id')) {
                $table->foreignId('previous_assignment_id')->nullable()->after('active_lock')->constrained('tz_asset_assignments')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'evidence_file_references')) {
                $table->json('evidence_file_references')->nullable()->after('reason');
            }
            if (! Schema::hasColumn('tz_asset_assignments', 'metadata')) {
                $table->json('metadata')->nullable()->after('evidence_file_references');
            }
        });
        Schema::table('tz_asset_assignments', function (Blueprint $table): void {
            $table->unique(['company_id', 'asset_id', 'active_lock'], 'tz_asset_one_active_custody_unique');
            $table->index(['company_id', 'expected_return_at', 'status'], 'tz_asset_custody_due_idx');
        });
    }

    private function createDamageReports(): void
    {
        Schema::create('tz_asset_damage_reports', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->foreignId('condition_record_id')->nullable()->constrained('tz_asset_condition_records')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->string('severity', 32);
            $table->string('status', 32)->default('open');
            $table->string('classification', 120)->nullable();
            $table->boolean('repair_recommended')->default(false);
            $table->text('description');
            $table->json('evidence_file_references')->nullable();
            $table->json('ai_suggestion')->nullable();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at');
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'asset_id', 'status'], 'tz_asset_damage_asset_status_idx');
        });
    }

    private function createMaintenancePlans(): void
    {
        Schema::create('tz_asset_maintenance_plans', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('interval_type', 32)->default('calendar');
            $table->unsignedInteger('interval_days')->nullable();
            $table->string('usage_metric', 64)->nullable();
            $table->decimal('usage_interval', 18, 3)->nullable();
            $table->decimal('last_completed_usage', 18, 3)->nullable();
            $table->timestamp('initial_due_at')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->boolean('calibration_required')->default(false);
            $table->char('service_provider_supplier_public_id', 26)->nullable();
            $table->foreignId('task_template_id')->nullable()->constrained('tz_task_templates')->nullOnDelete();
            $table->json('requirements')->nullable();
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'asset_id', 'active'], 'tz_asset_plan_asset_active_idx');
            $table->index(['company_id', 'next_due_at'], 'tz_asset_plan_due_idx');
        });
    }

    private function expandMaintenanceRecords(): void
    {
        Schema::table('tz_asset_maintenance_records', function (Blueprint $table): void {
            if (! Schema::hasColumn('tz_asset_maintenance_records', 'maintenance_plan_id')) {
                $table->foreignId('maintenance_plan_id')->nullable()->after('asset_id')->constrained('tz_asset_maintenance_plans')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_asset_maintenance_records', 'reference')) {
                $table->string('reference', 120)->nullable()->after('maintenance_plan_id');
            }
            if (! Schema::hasColumn('tz_asset_maintenance_records', 'priority')) {
                $table->string('priority', 24)->default('normal')->after('status');
            }
            if (! Schema::hasColumn('tz_asset_maintenance_records', 'due_at')) {
                $table->timestamp('due_at')->nullable()->after('priority');
            }
            if (! Schema::hasColumn('tz_asset_maintenance_records', 'downtime_started_at')) {
                $table->timestamp('downtime_started_at')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('tz_asset_maintenance_records', 'downtime_ended_at')) {
                $table->timestamp('downtime_ended_at')->nullable()->after('downtime_started_at');
            }
            if (! Schema::hasColumn('tz_asset_maintenance_records', 'root_cause')) {
                $table->text('root_cause')->nullable()->after('findings');
            }
            if (! Schema::hasColumn('tz_asset_maintenance_records', 'parts_references')) {
                $table->json('parts_references')->nullable()->after('actions_taken');
            }
            if (! Schema::hasColumn('tz_asset_maintenance_records', 'currency')) {
                $table->char('currency', 3)->nullable()->after('cost');
            }
            if (! Schema::hasColumn('tz_asset_maintenance_records', 'return_to_service_approved_by_user_id')) {
                $table->foreignId('return_to_service_approved_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_asset_maintenance_records', 'metadata')) {
                $table->json('metadata')->nullable()->after('notes');
            }
        });
        Schema::table('tz_asset_maintenance_records', function (Blueprint $table): void {
            $table->index(['company_id', 'due_at', 'status'], 'tz_asset_maintenance_due_idx');
        });
    }

    private function expandMeterReadings(): void
    {
        Schema::table('tz_asset_meter_readings', function (Blueprint $table): void {
            if (! Schema::hasColumn('tz_asset_meter_readings', 'metadata')) {
                $table->json('metadata')->nullable()->after('notes');
            }
        });
    }

    private function createCalibrations(): void
    {
        Schema::create('tz_asset_calibrations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->foreignId('maintenance_plan_id')->nullable()->constrained('tz_asset_maintenance_plans')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('result', 24);
            $table->string('standard', 160)->nullable();
            $table->string('tolerance', 160)->nullable();
            $table->json('measurements')->nullable();
            $table->string('certificate_file_reference', 255)->nullable();
            $table->json('evidence_file_references')->nullable();
            $table->foreignId('calibrated_by_worker_id')->nullable()->constrained('tz_workers')->nullOnDelete();
            $table->char('service_provider_supplier_public_id', 26)->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignId('return_to_service_approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('return_to_service_approved_at')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'asset_id', 'result'], 'tz_asset_calibration_asset_result_idx');
            $table->index(['company_id', 'due_at'], 'tz_asset_calibration_due_idx');
        });
    }

    private function createWarranties(): void
    {
        Schema::create('tz_asset_warranties', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->char('provider_supplier_public_id', 26)->nullable();
            $table->string('provider_name', 200)->nullable();
            $table->string('warranty_number', 160)->nullable();
            $table->date('starts_on');
            $table->date('expires_on');
            $table->json('terms')->nullable();
            $table->string('warranty_file_reference', 255)->nullable();
            $table->char('supply_purchase_order_public_id', 26)->nullable();
            $table->char('supply_goods_receipt_item_public_id', 26)->nullable();
            $table->string('status', 32)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'asset_id', 'status'], 'tz_asset_warranty_asset_status_idx');
            $table->index(['company_id', 'expires_on'], 'tz_asset_warranty_expiry_idx');
        });
    }

    private function createWarrantyClaims(): void
    {
        Schema::create('tz_asset_warranty_claims', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->foreignId('warranty_id')->constrained('tz_asset_warranties')->cascadeOnDelete();
            $table->string('claim_reference', 160)->nullable();
            $table->string('state', 32)->default('draft');
            $table->text('description');
            $table->timestamp('claimed_at')->nullable();
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('tz_work_orders')->nullOnDelete();
            $table->json('evidence_file_references')->nullable();
            $table->string('outcome', 40)->nullable();
            $table->foreignId('replacement_asset_id')->nullable()->constrained('tz_assets')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'asset_id', 'state'], 'tz_asset_claim_asset_state_idx');
        });
    }

    private function createDocumentLinks(): void
    {
        Schema::create('tz_asset_document_links', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('tz_assets')->cascadeOnDelete();
            $table->string('file_reference', 255);
            $table->string('document_type', 40);
            $table->string('related_type', 80)->nullable();
            $table->char('related_public_id', 26)->nullable();
            $table->string('title', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'asset_id', 'file_reference'], 'tz_asset_document_file_unique');
            $table->index(['company_id', 'related_type', 'related_public_id'], 'tz_asset_document_related_idx');
        });
    }

    private function createSupplyHandoffs(): void
    {
        Schema::create('tz_asset_supply_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('company_id')->constrained('tz_companies')->cascadeOnDelete();
            $table->string('idempotency_key', 191);
            $table->foreignId('inventory_item_id')->nullable()->constrained('tz_inventory_items')->nullOnDelete();
            $table->char('supply_item_public_id', 26);
            $table->char('supply_goods_receipt_item_public_id', 26)->nullable();
            $table->char('supply_purchase_order_public_id', 26)->nullable();
            $table->char('supplier_public_id', 26)->nullable();
            $table->string('serial_number', 150)->nullable();
            $table->string('source_payload_hash', 64)->nullable();
            $table->json('source_payload')->nullable();
            $table->string('status', 32)->default('pending');
            $table->foreignId('asset_id')->nullable()->constrained('tz_assets')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key'], 'tz_asset_handoff_idempotency_unique');
            $table->unique(['company_id', 'asset_id'], 'tz_asset_handoff_asset_unique');
        });
    }

    private function expandStatusHistory(): void
    {
        Schema::table('tz_asset_status_history', function (Blueprint $table): void {
            if (! Schema::hasColumn('tz_asset_status_history', 'override_used')) {
                $table->boolean('override_used')->default(false)->after('reason');
            }
            if (! Schema::hasColumn('tz_asset_status_history', 'approved_by_user_id')) {
                $table->foreignId('approved_by_user_id')->nullable()->after('changed_by_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('tz_asset_status_history', 'correlation_id')) {
                $table->string('correlation_id', 120)->nullable()->after('approved_by_user_id');
            }
            if (! Schema::hasColumn('tz_asset_status_history', 'evidence_file_references')) {
                $table->json('evidence_file_references')->nullable()->after('correlation_id');
            }
            if (! Schema::hasColumn('tz_asset_status_history', 'context')) {
                $table->json('context')->nullable()->after('evidence_file_references');
            }
        });
    }

    private function backfillCustodyLock(): void
    {
        $groups = DB::table('tz_asset_assignments')
            ->select(['company_id', 'asset_id'])
            ->where('active', true)
            ->groupBy(['company_id', 'asset_id'])
            ->get();

        foreach ($groups as $group) {
            $active = DB::table('tz_asset_assignments')
                ->where('company_id', $group->company_id)
                ->where('asset_id', $group->asset_id)
                ->where('active', true)
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->get();

            foreach ($active as $index => $assignment) {
                DB::table('tz_asset_assignments')->where('id', $assignment->id)->update($index === 0
                    ? ['status' => 'active', 'active_lock' => 'active', 'updated_at' => now()]
                    : ['active' => false, 'status' => 'superseded', 'active_lock' => null, 'ends_at' => $assignment->ends_at ?? now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tz_asset_status_history', function (Blueprint $table): void {
            $table->dropForeign(['approved_by_user_id']);
            $table->dropColumn(['override_used', 'approved_by_user_id', 'correlation_id', 'evidence_file_references', 'context']);
        });

        Schema::dropIfExists('tz_asset_supply_handoffs');
        Schema::dropIfExists('tz_asset_document_links');
        Schema::dropIfExists('tz_asset_warranty_claims');
        Schema::dropIfExists('tz_asset_warranties');
        Schema::dropIfExists('tz_asset_calibrations');

        Schema::table('tz_asset_meter_readings', fn (Blueprint $table) => $table->dropColumn('metadata'));
        Schema::table('tz_asset_maintenance_records', function (Blueprint $table): void {
            $table->dropForeign(['maintenance_plan_id']);
            $table->dropForeign(['return_to_service_approved_by_user_id']);
            $table->dropIndex('tz_asset_maintenance_due_idx');
            $table->dropColumn(['maintenance_plan_id', 'reference', 'priority', 'due_at', 'downtime_started_at', 'downtime_ended_at', 'root_cause', 'parts_references', 'currency', 'return_to_service_approved_by_user_id', 'metadata']);
        });
        Schema::dropIfExists('tz_asset_maintenance_plans');
        Schema::dropIfExists('tz_asset_damage_reports');

        Schema::table('tz_asset_assignments', function (Blueprint $table): void {
            foreach ([['job_id'], ['storage_location_id'], ['custodian_user_id'], ['issued_by_user_id'], ['returned_by_user_id'], ['condition_out_id'], ['condition_in_id'], ['acknowledged_by_user_id'], ['previous_assignment_id']] as $foreign) {
                $table->dropForeign($foreign);
            }
            $table->dropUnique('tz_asset_one_active_custody_unique');
            $table->dropIndex('tz_asset_custody_due_idx');
            $table->dropColumn(['allocation_mode', 'team_public_id', 'vehicle_public_id', 'job_id', 'storage_location_id', 'premises_zone_public_id', 'custodian_user_id', 'issued_by_user_id', 'returned_by_user_id', 'expected_return_at', 'returned_at', 'condition_out_id', 'condition_in_id', 'acknowledged_by_user_id', 'acknowledged_at', 'status', 'active_lock', 'previous_assignment_id', 'evidence_file_references', 'metadata']);
        });

        Schema::dropIfExists('tz_asset_condition_records');
        Schema::dropIfExists('tz_asset_identifiers');

        Schema::table('tz_assets', function (Blueprint $table): void {
            $table->dropForeign(['storage_location_id']);
            $table->dropForeign(['current_job_id']);
            $table->dropUnique('tz_asset_company_serial_unique');
            $table->dropIndex('tz_asset_company_supply_item_idx');
            $table->dropIndex('tz_asset_company_calibration_due_idx');
            $table->dropColumn(['premises_zone_public_id', 'storage_location_id', 'current_team_public_id', 'current_vehicle_public_id', 'current_job_id', 'supply_item_public_id', 'supply_goods_receipt_item_public_id', 'supply_purchase_order_public_id', 'supplier_public_id', 'currency', 'warranty_starts_on', 'next_calibration_due_on', 'last_seen_at', 'lock_version', 'retired_at', 'disposed_at', 'written_off_at']);
        });

        Schema::dropIfExists('tz_asset_migration_conflicts');
        Schema::table('tz_asset_categories', fn (Blueprint $table) => $table->dropColumn(['requires_maintenance', 'requires_calibration', 'settings']));
    }
};

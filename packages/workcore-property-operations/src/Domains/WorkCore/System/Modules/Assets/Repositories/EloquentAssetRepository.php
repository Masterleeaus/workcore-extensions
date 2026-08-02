<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Assets\Contracts\AssetRepositoryContract;
use App\Domains\WorkCore\System\Modules\Assets\Services\AssetAssignmentPolicy;
use App\Domains\WorkCore\System\Modules\Assets\Services\AssetQualificationPolicy;
use App\Domains\WorkCore\System\Modules\Assets\Services\AssetStatusPolicy;
use App\Domains\WorkCore\System\Modules\Assets\Services\WarrantyClaimPolicy;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use App\Domains\WorkCore\System\Validation\CompanyScopedReferenceValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentAssetRepository extends TenantScopedRepository implements AssetRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private CompanyScopedReferenceValidator $references,
        private AssetStatusPolicy $statusPolicy,
        private AssetAssignmentPolicy $assignmentPolicy,
        private AssetQualificationPolicy $qualificationPolicy,
        private WarrantyClaimPolicy $warrantyClaimPolicy,
    ) {
        parent::__construct($db, $tenant);
    }

    public function search(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $query = $this->db->table('tz_assets as assets')
            ->leftJoin('tz_asset_categories as categories', 'categories.id', '=', 'assets.category_id')
            ->leftJoin('tz_premises as premises', 'premises.id', '=', 'assets.premises_id')
            ->leftJoin('tz_workers as workers', 'workers.id', '=', 'assets.custodian_worker_id')
            ->leftJoin('tz_stock_locations as storage', 'storage.id', '=', 'assets.storage_location_id')
            ->leftJoin('tz_work_orders as jobs', 'jobs.id', '=', 'assets.current_job_id')
            ->where('assets.company_id', $companyId)
            ->whereNull('assets.deleted_at');

        if ($q = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function (Builder $builder) use ($q): void {
                $builder->where('assets.name', 'like', "%{$q}%")
                    ->orWhere('assets.asset_number', 'like', "%{$q}%")
                    ->orWhere('assets.serial_number', 'like', "%{$q}%")
                    ->orWhere('assets.barcode', 'like', "%{$q}%")
                    ->orWhere('assets.registration_number', 'like', "%{$q}%");
            });
        }
        foreach (['status' => 'assets.status', 'asset_type' => 'assets.asset_type', 'criticality' => 'assets.criticality', 'condition' => 'assets.condition'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (! empty($filters['premises_public_id'])) {
            $query->where('premises.public_id', $filters['premises_public_id']);
        }
        if (! empty($filters['category_public_id'])) {
            $query->where('categories.public_id', $filters['category_public_id']);
        }
        if (! empty($filters['worker_public_id'])) {
            $query->where('workers.public_id', $filters['worker_public_id']);
        }
        if (! empty($filters['supply_item_public_id'])) {
            $query->where('assets.supply_item_public_id', $filters['supply_item_public_id']);
        }
        if (! empty($filters['due_before'])) {
            $query->where(function (Builder $builder) use ($filters): void {
                $builder->whereDate('assets.next_service_due_on', '<=', $filters['due_before'])
                    ->orWhereDate('assets.next_inspection_due_on', '<=', $filters['due_before'])
                    ->orWhereDate('assets.next_calibration_due_on', '<=', $filters['due_before']);
            });
        }

        return $query->select([
            'assets.*',
            'categories.public_id as category_public_id', 'categories.name as category_name',
            'premises.public_id as premises_public_id', 'premises.name as premises_name',
            'workers.public_id as custodian_worker_public_id', 'workers.display_name as custodian_worker_name',
            'storage.public_id as storage_location_public_id', 'storage.name as storage_location_name',
            'jobs.public_id as current_job_public_id', 'jobs.title as current_job_title',
        ])->orderBy('assets.name')->paginate(max(1, min($perPage, 100)));
    }

    public function createCategory(array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        $parentId = ! empty($data['parent_public_id'])
            ? $this->references->requirePublicRecordId($companyId, 'tz_asset_categories', (string) $data['parent_public_id'], 'parent_public_id')
            : null;
        $code = Str::slug((string) ($data['code'] ?? $data['name']), '_');
        if ($this->db->table('tz_asset_categories')->where('company_id', $companyId)->where('code', $code)->whereNull('deleted_at')->exists()) {
            throw new InvalidArgumentException('Asset category code already exists for this company.');
        }
        $id = $this->db->table('tz_asset_categories')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'parent_id' => $parentId,
            'name' => $data['name'],
            'code' => $code,
            'asset_scope' => $data['asset_scope'] ?? 'general',
            'description' => $data['description'] ?? null,
            'requires_serial_number' => (bool) ($data['requires_serial_number'] ?? false),
            'requires_service_schedule' => (bool) ($data['requires_service_schedule'] ?? false),
            'requires_maintenance' => (bool) ($data['requires_maintenance'] ?? false),
            'requires_calibration' => (bool) ($data['requires_calibration'] ?? false),
            'settings' => $this->json($data['settings'] ?? null),
            'active' => (bool) ($data['active'] ?? true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_asset_categories')->where('id', $id)->first();
    }

    public function createAsset(array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        $categoryId = ! empty($data['category_public_id'])
            ? $this->references->requirePublicRecordId($companyId, 'tz_asset_categories', (string) $data['category_public_id'], 'category_public_id') : null;
        $premisesId = ! empty($data['premises_public_id'])
            ? $this->references->requirePublicRecordId($companyId, 'tz_premises', (string) $data['premises_public_id'], 'premises_public_id') : null;
        $parentAssetId = ! empty($data['parent_asset_public_id'])
            ? $this->references->requirePublicRecordId($companyId, 'tz_assets', (string) $data['parent_asset_public_id'], 'parent_asset_public_id') : null;
        $workerId = ! empty($data['custodian_worker_public_id'])
            ? $this->references->requirePublicRecordId($companyId, 'tz_workers', (string) $data['custodian_worker_public_id'], 'custodian_worker_public_id') : null;
        $storageLocationId = ! empty($data['storage_location_public_id'])
            ? $this->references->requirePublicRecordId($companyId, 'tz_stock_locations', (string) $data['storage_location_public_id'], 'storage_location_public_id') : null;
        $jobId = ! empty($data['current_job_public_id'])
            ? $this->references->requirePublicRecordId($companyId, 'tz_work_orders', (string) $data['current_job_public_id'], 'current_job_public_id') : null;

        $status = (string) ($data['status'] ?? 'available');
        $this->statusPolicy->assertInitial($status);
        $serial = $this->nullableTrim($data['serial_number'] ?? null);
        if ($serial !== null && $this->db->table('tz_assets')->where('company_id', $companyId)->where('serial_number', $serial)->whereNull('deleted_at')->exists()) {
            throw new InvalidArgumentException('Asset serial number already exists for this company.');
        }
        if ($categoryId !== null) {
            $category = $this->db->table('tz_asset_categories')->where('company_id', $companyId)->where('id', $categoryId)->first();
            if ($category && (bool) $category->requires_serial_number && $serial === null) {
                throw new InvalidArgumentException('This asset category requires a serial number.');
            }
        }

        $publicId = (string) Str::ulid();
        $assetNumber = trim((string) ($data['asset_number'] ?? '')) ?: 'AST-' . Str::upper(substr($publicId, -8));
        if ($this->db->table('tz_assets')->where('company_id', $companyId)->where('asset_number', $assetNumber)->whereNull('deleted_at')->exists()) {
            throw new InvalidArgumentException('Asset number already exists for this company.');
        }

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $categoryId, $premisesId, $parentAssetId, $workerId, $storageLocationId, $jobId, $status, $serial, $publicId, $assetNumber): array {
            $id = $this->db->table('tz_assets')->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'category_id' => $categoryId,
                'premises_id' => $premisesId,
                'premises_zone_public_id' => $this->nullableTrim($data['premises_zone_public_id'] ?? null),
                'storage_location_id' => $storageLocationId,
                'parent_asset_id' => $parentAssetId,
                'custodian_worker_id' => $workerId,
                'current_team_public_id' => $this->nullableTrim($data['current_team_public_id'] ?? null),
                'current_vehicle_public_id' => $this->nullableTrim($data['current_vehicle_public_id'] ?? null),
                'current_job_id' => $jobId,
                'asset_number' => $assetNumber,
                'name' => $data['name'],
                'asset_type' => $data['asset_type'] ?? 'equipment',
                'ownership_type' => $data['ownership_type'] ?? 'company_owned',
                'status' => $status,
                'condition' => $data['condition'] ?? 'good',
                'criticality' => $data['criticality'] ?? 'standard',
                'manufacturer' => $data['manufacturer'] ?? null,
                'model' => $data['model'] ?? null,
                'serial_number' => $serial,
                'barcode' => $this->nullableTrim($data['barcode'] ?? null),
                'registration_number' => $data['registration_number'] ?? null,
                'location_description' => $data['location_description'] ?? null,
                'installed_on' => $data['installed_on'] ?? null,
                'acquired_on' => $data['acquired_on'] ?? null,
                'purchase_cost' => $data['purchase_cost'] ?? null,
                'currency' => $this->nullableTrim($data['currency'] ?? null),
                'warranty_starts_on' => $data['warranty_starts_on'] ?? null,
                'warranty_expires_on' => $data['warranty_expires_on'] ?? null,
                'supply_item_public_id' => $this->nullableTrim($data['supply_item_public_id'] ?? null),
                'supply_goods_receipt_item_public_id' => $this->nullableTrim($data['supply_goods_receipt_item_public_id'] ?? null),
                'supply_purchase_order_public_id' => $this->nullableTrim($data['supply_purchase_order_public_id'] ?? null),
                'supplier_public_id' => $this->nullableTrim($data['supplier_public_id'] ?? null),
                'next_service_due_on' => $data['next_service_due_on'] ?? null,
                'next_inspection_due_on' => $data['next_inspection_due_on'] ?? null,
                'next_calibration_due_on' => $data['next_calibration_due_on'] ?? null,
                'last_seen_at' => $data['last_seen_at'] ?? null,
                'lock_version' => 0,
                'specifications' => $this->json($data['specifications'] ?? null),
                'metadata' => $this->json($data['metadata'] ?? null),
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->appendStatusHistory($companyId, $id, null, $status, null, (string) ($data['condition'] ?? 'good'), 'Asset created', $actorId, null, false, $data);
            if (! empty($data['barcode'])) {
                $this->insertIdentifier($id, $companyId, 'barcode', (string) $data['barcode'], [], true);
            }
            return $this->assetById($id);
        }, 3);
    }

    public function assignAsset(string $assetPublicId, array $data, int $companyId, int $actorId): array
    {
        return $this->allocate($assetPublicId, $data + ['allocation_mode' => 'assignment', 'target_state' => 'assigned'], $companyId, $actorId, false);
    }

    public function checkoutAsset(string $assetPublicId, array $data, int $companyId, int $actorId): array
    {
        return $this->allocate($assetPublicId, $data + ['allocation_mode' => $data['allocation_mode'] ?? 'loan', 'target_state' => 'checked_out'], $companyId, $actorId, false);
    }

    public function checkinAsset(string $assetPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        return $this->db->transaction(function () use ($assetPublicId, $data, $companyId, $actorId): array {
            $asset = $this->asset($assetPublicId, $companyId, true);
            $assignment = $this->activeAssignment((int) $asset->id, $companyId, true);
            if (! $assignment) {
                throw new InvalidArgumentException('Asset has no active custody to check in.');
            }
            $conditionInId = null;
            if (! empty($data['condition_in'])) {
                $condition = $this->insertCondition((int) $asset->id, $companyId, $actorId, $data['condition_in'] + ['context' => 'checkin', 'assignment_id' => $assignment->id]);
                $conditionInId = $condition['id'];
            }
            $this->db->table('tz_asset_assignments')->where('id', $assignment->id)->update([
                'active' => false,
                'status' => 'returned',
                'active_lock' => null,
                'ends_at' => $data['returned_at'] ?? now(),
                'returned_at' => $data['returned_at'] ?? now(),
                'returned_by_user_id' => $actorId,
                'condition_in_id' => $conditionInId,
                'reason' => $data['reason'] ?? $assignment->reason,
                'updated_at' => now(),
            ]);
            $this->clearCurrentTarget((int) $asset->id, $actorId);
            $targetStatus = (string) ($data['target_status'] ?? 'available');
            $this->transitionLocked($asset, $targetStatus, $data + ['reason' => $data['reason'] ?? 'Asset checked in'], $actorId);
            return [
                'asset' => $this->assetById((int) $asset->id),
                'assignment' => (array) $this->db->table('tz_asset_assignments')->where('id', $assignment->id)->first(),
                'condition' => $conditionInId ? (array) $this->db->table('tz_asset_condition_records')->where('id', $conditionInId)->first() : null,
            ];
        }, 3);
    }

    public function transferAsset(string $assetPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        return $this->db->transaction(function () use ($assetPublicId, $data, $companyId, $actorId): array {
            $asset = $this->asset($assetPublicId, $companyId, true);
            $previous = $this->activeAssignment((int) $asset->id, $companyId, true);
            if (! $previous) {
                throw new InvalidArgumentException('Asset has no active custody to transfer.');
            }
            $conditionInId = null;
            if (! empty($data['condition_in'])) {
                $condition = $this->insertCondition((int) $asset->id, $companyId, $actorId, $data['condition_in'] + ['context' => 'transfer_in', 'assignment_id' => $previous->id]);
                $conditionInId = $condition['id'];
            }
            $this->db->table('tz_asset_assignments')->where('id', $previous->id)->update([
                'active' => false, 'status' => 'transferred', 'active_lock' => null,
                'ends_at' => now(), 'returned_at' => now(), 'returned_by_user_id' => $actorId,
                'condition_in_id' => $conditionInId, 'updated_at' => now(),
            ]);
            $payload = $data + [
                'previous_assignment_id' => $previous->id,
                'allocation_mode' => $data['allocation_mode'] ?? 'transfer',
                'target_state' => $data['target_state'] ?? 'assigned',
            ];
            return $this->allocateLocked($asset, $payload, $companyId, $actorId, true);
        }, 3);
    }

    public function changeStatus(string $assetPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        return $this->db->transaction(function () use ($assetPublicId, $data, $companyId, $actorId): array {
            $asset = $this->asset($assetPublicId, $companyId, true);
            $this->transitionLocked($asset, (string) $data['status'], $data, $actorId);
            if (in_array((string) $data['status'], ['retired', 'disposed', 'written_off'], true)) {
                $this->closeActiveAssignment((int) $asset->id, $companyId, $actorId, (string) $data['status']);
                $this->clearCurrentTarget((int) $asset->id, $actorId);
            }
            return $this->assetById((int) $asset->id);
        }, 3);
    }

    public function recordCondition(string $assetPublicId, array $data, int $companyId, int $actorId): array
    {
        $asset = $this->asset($assetPublicId, $companyId);
        return $this->db->transaction(function () use ($asset, $data, $companyId, $actorId): array {
            $record = $this->insertCondition((int) $asset->id, $companyId, $actorId, $data);
            $this->db->table('tz_assets')->where('id', $asset->id)->update([
                'condition' => $data['grade'], 'last_seen_at' => $data['recorded_at'] ?? now(),
                'updated_by_user_id' => $actorId, 'updated_at' => now(),
            ]);
            return $record;
        }, 3);
    }

    public function reportDamage(string $assetPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        return $this->db->transaction(function () use ($assetPublicId, $data, $companyId, $actorId): array {
            $asset = $this->asset($assetPublicId, $companyId, true);
            $conditionId = null;
            if (! empty($data['condition'])) {
                $condition = $this->insertCondition((int) $asset->id, $companyId, $actorId, $data['condition'] + ['context' => 'damage']);
                $conditionId = $condition['id'];
            }
            $workOrderId = ! empty($data['work_order_public_id'])
                ? $this->references->requirePublicRecordId($companyId, 'tz_work_orders', (string) $data['work_order_public_id'], 'work_order_public_id') : null;
            $id = $this->db->table('tz_asset_damage_reports')->insertGetId([
                'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'asset_id' => $asset->id,
                'condition_record_id' => $conditionId, 'work_order_id' => $workOrderId,
                'severity' => $data['severity'], 'status' => $data['status'] ?? 'open',
                'classification' => $data['classification'] ?? null,
                'repair_recommended' => (bool) ($data['repair_recommended'] ?? false),
                'description' => $data['description'],
                'evidence_file_references' => $this->json($data['evidence_file_references'] ?? null),
                'ai_suggestion' => $this->json($data['ai_suggestion'] ?? null),
                'reported_by_user_id' => $actorId, 'reported_at' => $data['reported_at'] ?? now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ((string) $asset->status !== 'damaged') {
                $this->transitionLocked($asset, 'damaged', ['reason' => $data['description']], $actorId);
            }
            return (array) $this->db->table('tz_asset_damage_reports')->where('id', $id)->first();
        }, 3);
    }

    public function createMaintenancePlan(string $assetPublicId, array $data, int $companyId, int $actorId): array
    {
        $asset = $this->asset($assetPublicId, $companyId);
        $intervalType = (string) ($data['interval_type'] ?? 'calendar');
        if (! in_array($intervalType, ['calendar', 'usage', 'hybrid'], true)) {
            throw new InvalidArgumentException('Unsupported maintenance interval type.');
        }
        if (in_array($intervalType, ['calendar', 'hybrid'], true) && (int) ($data['interval_days'] ?? 0) < 1) {
            throw new InvalidArgumentException('Calendar maintenance requires interval_days.');
        }
        if (in_array($intervalType, ['usage', 'hybrid'], true) && (trim((string) ($data['usage_metric'] ?? '')) === '' || (float) ($data['usage_interval'] ?? 0) <= 0)) {
            throw new InvalidArgumentException('Usage maintenance requires metric and interval.');
        }
        $taskTemplateId = ! empty($data['task_template_public_id'])
            ? $this->references->requirePublicRecordId($companyId, 'tz_task_templates', (string) $data['task_template_public_id'], 'task_template_public_id') : null;
        $id = $this->db->table('tz_asset_maintenance_plans')->insertGetId([
            'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'asset_id' => $asset->id,
            'name' => $data['name'], 'description' => $data['description'] ?? null,
            'interval_type' => $intervalType, 'interval_days' => $data['interval_days'] ?? null,
            'usage_metric' => $data['usage_metric'] ?? null, 'usage_interval' => $data['usage_interval'] ?? null,
            'last_completed_usage' => $data['last_completed_usage'] ?? null,
            'initial_due_at' => $data['initial_due_at'] ?? null,
            'next_due_at' => $data['next_due_at'] ?? $data['initial_due_at'] ?? null,
            'calibration_required' => (bool) ($data['calibration_required'] ?? false),
            'service_provider_supplier_public_id' => $this->nullableTrim($data['service_provider_supplier_public_id'] ?? null),
            'task_template_id' => $taskTemplateId, 'requirements' => $this->json($data['requirements'] ?? null),
            'active' => (bool) ($data['active'] ?? true), 'metadata' => $this->json($data['metadata'] ?? null),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_asset_maintenance_plans')->where('id', $id)->first();
    }

    public function recordMaintenance(string $assetPublicId, array $data, int $companyId, int $actorId): array
    {
        $asset = $this->asset($assetPublicId, $companyId);
        $planId = ! empty($data['maintenance_plan_public_id'])
            ? $this->references->requirePublicRecordId($companyId, 'tz_asset_maintenance_plans', (string) $data['maintenance_plan_public_id'], 'maintenance_plan_public_id') : null;
        $workOrderId = ! empty($data['work_order_public_id'])
            ? $this->references->requirePublicRecordId($companyId, 'tz_work_orders', (string) $data['work_order_public_id'], 'work_order_public_id') : null;
        $appointmentId = ! empty($data['appointment_public_id'])
            ? $this->references->requirePublicRecordId($companyId, 'tz_appointments', (string) $data['appointment_public_id'], 'appointment_public_id') : null;
        $workerId = ! empty($data['worker_public_id'])
            ? $this->references->requirePublicRecordId($companyId, 'tz_workers', (string) $data['worker_public_id'], 'worker_public_id') : null;
        $status = (string) ($data['status'] ?? 'scheduled');

        return $this->db->transaction(function () use ($asset, $data, $companyId, $actorId, $planId, $workOrderId, $appointmentId, $workerId, $status): array {
            $id = $this->db->table('tz_asset_maintenance_records')->insertGetId([
                'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'asset_id' => $asset->id,
                'maintenance_plan_id' => $planId, 'reference' => $data['reference'] ?? null,
                'work_order_id' => $workOrderId, 'appointment_id' => $appointmentId,
                'performed_by_worker_id' => $workerId, 'maintenance_type' => $data['maintenance_type'],
                'status' => $status, 'priority' => $data['priority'] ?? 'normal',
                'due_at' => $data['due_at'] ?? null, 'vendor_name' => $data['vendor_name'] ?? null,
                'scheduled_for' => $data['scheduled_for'] ?? null, 'started_at' => $data['started_at'] ?? null,
                'completed_at' => $data['completed_at'] ?? ($status === 'completed' ? now() : null),
                'downtime_started_at' => $data['downtime_started_at'] ?? null,
                'downtime_ended_at' => $data['downtime_ended_at'] ?? null,
                'cost' => $data['cost'] ?? null, 'currency' => $data['currency'] ?? null,
                'findings' => $data['findings'] ?? null, 'root_cause' => $data['root_cause'] ?? null,
                'actions_taken' => $data['actions_taken'] ?? null,
                'parts_references' => $this->json($data['parts_references'] ?? null),
                'next_due_on' => $data['next_due_on'] ?? null,
                'evidence' => $this->json($data['evidence_file_references'] ?? $data['evidence'] ?? null),
                'notes' => $data['notes'] ?? null, 'metadata' => $this->json($data['metadata'] ?? null),
                'created_by_user_id' => $actorId,
                'return_to_service_approved_by_user_id' => $data['return_to_service_approved_by_user_id'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($status === 'completed') {
                $this->applyMaintenanceCompletion((int) $asset->id, $planId, $data, $actorId);
            }
            return (array) $this->db->table('tz_asset_maintenance_records')->where('id', $id)->first();
        }, 3);
    }

    public function startMaintenance(string $assetPublicId, string $maintenancePublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        return $this->db->transaction(function () use ($assetPublicId, $maintenancePublicId, $data, $companyId, $actorId): array {
            $asset = $this->asset($assetPublicId, $companyId, true);
            $maintenance = $this->maintenance($maintenancePublicId, (int) $asset->id, $companyId, true);
            if (! in_array((string) $maintenance->status, ['due', 'scheduled', 'awaiting_parts'], true)) {
                throw new InvalidArgumentException('Maintenance work cannot be started from its current status.');
            }
            $this->db->table('tz_asset_maintenance_records')->where('id', $maintenance->id)->update([
                'status' => 'in_progress', 'started_at' => $data['started_at'] ?? now(),
                'downtime_started_at' => $data['downtime_started_at'] ?? now(),
                'performed_by_worker_id' => ! empty($data['worker_public_id'])
                    ? $this->references->requirePublicRecordId($companyId, 'tz_workers', (string) $data['worker_public_id'], 'worker_public_id')
                    : $maintenance->performed_by_worker_id,
                'updated_at' => now(),
            ]);
            if ((string) $asset->status !== 'under_maintenance') {
                $this->transitionLocked($asset, 'under_maintenance', ['reason' => $data['reason'] ?? 'Maintenance started'], $actorId);
            }
            return (array) $this->db->table('tz_asset_maintenance_records')->where('id', $maintenance->id)->first();
        }, 3);
    }

    public function completeMaintenance(string $assetPublicId, string $maintenancePublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        return $this->db->transaction(function () use ($assetPublicId, $maintenancePublicId, $data, $companyId, $actorId): array {
            $asset = $this->asset($assetPublicId, $companyId, true);
            $maintenance = $this->maintenance($maintenancePublicId, (int) $asset->id, $companyId, true);
            if (! in_array((string) $maintenance->status, ['in_progress', 'scheduled', 'due', 'awaiting_parts'], true)) {
                throw new InvalidArgumentException('Maintenance work cannot be completed from its current status.');
            }
            $approvedBy = (int) ($data['return_to_service_approved_by_user_id'] ?? 0);
            if ($approvedBy < 1) {
                throw new InvalidArgumentException('Maintenance completion requires return-to-service approval.');
            }
            $this->references->requireActiveMember($companyId, $approvedBy, 'return_to_service_approved_by_user_id');
            $this->db->table('tz_asset_maintenance_records')->where('id', $maintenance->id)->update([
                'status' => 'completed', 'completed_at' => $data['completed_at'] ?? now(),
                'downtime_ended_at' => $data['downtime_ended_at'] ?? now(),
                'findings' => $data['findings'] ?? $maintenance->findings,
                'root_cause' => $data['root_cause'] ?? $maintenance->root_cause,
                'actions_taken' => $data['actions_taken'] ?? $maintenance->actions_taken,
                'parts_references' => $this->json($data['parts_references'] ?? null) ?? $maintenance->parts_references,
                'cost' => $data['cost'] ?? $maintenance->cost, 'currency' => $data['currency'] ?? $maintenance->currency,
                'next_due_on' => $data['next_due_on'] ?? $maintenance->next_due_on,
                'evidence' => $this->json($data['evidence_file_references'] ?? null) ?? $maintenance->evidence,
                'return_to_service_approved_by_user_id' => $approvedBy, 'updated_at' => now(),
            ]);
            $this->applyMaintenanceCompletion((int) $asset->id, $maintenance->maintenance_plan_id ? (int) $maintenance->maintenance_plan_id : null, $data, $actorId);
            $target = $this->activeAssignment((int) $asset->id, $companyId) ? 'assigned' : 'available';
            if ((string) $asset->status !== $target) {
                $this->transitionLocked($asset, $target, ['reason' => $data['reason'] ?? 'Maintenance completed', 'approved_by_user_id' => $approvedBy], $actorId);
            }
            return (array) $this->db->table('tz_asset_maintenance_records')->where('id', $maintenance->id)->first();
        }, 3);
    }

    public function recordMeterReading(string $assetPublicId, array $data, int $companyId, int $actorId): array
    {
        $asset = $this->asset($assetPublicId, $companyId);
        $workerId = ! empty($data['worker_public_id'])
            ? $this->references->requirePublicRecordId($companyId, 'tz_workers', (string) $data['worker_public_id'], 'worker_public_id') : null;
        return $this->db->transaction(function () use ($asset, $data, $companyId, $actorId, $workerId): array {
            $id = $this->db->table('tz_asset_meter_readings')->insertGetId([
                'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'asset_id' => $asset->id,
                'worker_id' => $workerId, 'reading_type' => $data['reading_type'],
                'reading_value' => $data['reading_value'], 'unit' => $data['unit'],
                'read_at' => $data['read_at'] ?? now(), 'source' => $data['source'] ?? 'manual',
                'notes' => $data['notes'] ?? null, 'metadata' => $this->json($data['metadata'] ?? null),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->markUsagePlansDue((int) $asset->id, $companyId, (string) $data['reading_type'], (float) $data['reading_value'], $actorId);
            return (array) $this->db->table('tz_asset_meter_readings')->where('id', $id)->first();
        }, 3);
    }

    public function recordCalibration(string $assetPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        return $this->db->transaction(function () use ($assetPublicId, $data, $companyId, $actorId): array {
            $asset = $this->asset($assetPublicId, $companyId, true);
            $result = (string) $data['result'];
            if (! in_array($result, ['passed', 'failed', 'conditional'], true)) {
                throw new InvalidArgumentException('Unsupported calibration result.');
            }
            $planId = ! empty($data['maintenance_plan_public_id'])
                ? $this->references->requirePublicRecordId($companyId, 'tz_asset_maintenance_plans', (string) $data['maintenance_plan_public_id'], 'maintenance_plan_public_id') : null;
            $workerId = ! empty($data['worker_public_id'])
                ? $this->references->requirePublicRecordId($companyId, 'tz_workers', (string) $data['worker_public_id'], 'worker_public_id') : null;
            $approvedBy = (int) ($data['return_to_service_approved_by_user_id'] ?? 0);
            if ($result === 'passed' && (bool) ($data['return_to_service'] ?? false) && $approvedBy < 1) {
                throw new InvalidArgumentException('Returning calibrated equipment to service requires approval.');
            }
            if ($approvedBy > 0) {
                $this->references->requireActiveMember($companyId, $approvedBy, 'return_to_service_approved_by_user_id');
            }
            if ($result === 'failed' && trim((string) ($data['failure_reason'] ?? '')) === '') {
                throw new InvalidArgumentException('Failed calibration requires a reason.');
            }
            $id = $this->db->table('tz_asset_calibrations')->insertGetId([
                'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'asset_id' => $asset->id,
                'maintenance_plan_id' => $planId, 'due_at' => $data['due_at'] ?? null,
                'completed_at' => $data['completed_at'] ?? now(), 'result' => $result,
                'standard' => $data['standard'] ?? null, 'tolerance' => $data['tolerance'] ?? null,
                'measurements' => $this->json($data['measurements'] ?? null),
                'certificate_file_reference' => $data['certificate_file_reference'] ?? null,
                'evidence_file_references' => $this->json($data['evidence_file_references'] ?? null),
                'calibrated_by_worker_id' => $workerId,
                'service_provider_supplier_public_id' => $this->nullableTrim($data['service_provider_supplier_public_id'] ?? null),
                'failure_reason' => $data['failure_reason'] ?? null,
                'return_to_service_approved_by_user_id' => $approvedBy ?: null,
                'return_to_service_approved_at' => $approvedBy ? now() : null,
                'next_due_at' => $data['next_due_at'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->db->table('tz_assets')->where('id', $asset->id)->update([
                'next_calibration_due_on' => $data['next_due_at'] ?? null,
                'updated_by_user_id' => $actorId, 'updated_at' => now(),
            ]);
            if ($result === 'failed') {
                $this->transitionLocked($asset, 'unavailable', ['reason' => $data['failure_reason']], $actorId);
            } elseif ((bool) ($data['return_to_service'] ?? false)) {
                $target = $this->activeAssignment((int) $asset->id, $companyId) ? 'assigned' : 'available';
                $this->transitionLocked($asset, $target, ['reason' => $data['reason'] ?? 'Calibration passed', 'approved_by_user_id' => $approvedBy], $actorId);
            }
            return (array) $this->db->table('tz_asset_calibrations')->where('id', $id)->first();
        }, 3);
    }

    public function createWarranty(string $assetPublicId, array $data, int $companyId, int $actorId): array
    {
        $asset = $this->asset($assetPublicId, $companyId);
        if (Carbon::parse((string) $data['expires_on'])->lt(Carbon::parse((string) $data['starts_on']))) {
            throw new InvalidArgumentException('Warranty expiry cannot precede its start date.');
        }
        $id = $this->db->table('tz_asset_warranties')->insertGetId([
            'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'asset_id' => $asset->id,
            'provider_supplier_public_id' => $this->nullableTrim($data['provider_supplier_public_id'] ?? null),
            'provider_name' => $data['provider_name'] ?? null, 'warranty_number' => $data['warranty_number'] ?? null,
            'starts_on' => $data['starts_on'], 'expires_on' => $data['expires_on'],
            'terms' => $this->json($data['terms'] ?? null),
            'warranty_file_reference' => $data['warranty_file_reference'] ?? null,
            'supply_purchase_order_public_id' => $this->nullableTrim($data['supply_purchase_order_public_id'] ?? null),
            'supply_goods_receipt_item_public_id' => $this->nullableTrim($data['supply_goods_receipt_item_public_id'] ?? null),
            'status' => $data['status'] ?? 'active', 'metadata' => $this->json($data['metadata'] ?? null),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->db->table('tz_assets')->where('id', $asset->id)->update([
            'warranty_starts_on' => $data['starts_on'], 'warranty_expires_on' => $data['expires_on'],
            'updated_by_user_id' => $actorId, 'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_asset_warranties')->where('id', $id)->first();
    }

    public function createWarrantyClaim(string $assetPublicId, string $warrantyPublicId, array $data, int $companyId, int $actorId): array
    {
        $asset = $this->asset($assetPublicId, $companyId);
        $warranty = $this->db->table('tz_asset_warranties')->where('company_id', $companyId)->where('asset_id', $asset->id)->where('public_id', $warrantyPublicId)->whereNull('deleted_at')->first();
        if (! $warranty) {
            throw new InvalidArgumentException('Warranty does not belong to this asset.');
        }
        $workOrderId = ! empty($data['work_order_public_id'])
            ? $this->references->requirePublicRecordId($companyId, 'tz_work_orders', (string) $data['work_order_public_id'], 'work_order_public_id') : null;
        $state = (string) ($data['state'] ?? 'submitted');
        $this->warrantyClaimPolicy->assertInitial($state);
        $id = $this->db->table('tz_asset_warranty_claims')->insertGetId([
            'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'asset_id' => $asset->id,
            'warranty_id' => $warranty->id, 'claim_reference' => $data['claim_reference'] ?? null,
            'state' => $state, 'description' => $data['description'],
            'claimed_at' => $data['claimed_at'] ?? now(), 'claimed_by_user_id' => $actorId,
            'work_order_id' => $workOrderId, 'evidence_file_references' => $this->json($data['evidence_file_references'] ?? null),
            'outcome' => null, 'replacement_asset_id' => null,
            'resolved_at' => null, 'resolution_notes' => null,
            'metadata' => $this->json($data['metadata'] ?? null), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_asset_warranty_claims')->where('id', $id)->first();
    }

    public function updateWarrantyClaim(string $assetPublicId, string $claimPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        return $this->db->transaction(function () use ($assetPublicId, $claimPublicId, $data, $companyId): array {
            $asset = $this->asset($assetPublicId, $companyId, true);
            $claim = $this->db->table('tz_asset_warranty_claims')
                ->where('company_id', $companyId)
                ->where('asset_id', $asset->id)
                ->where('public_id', $claimPublicId)
                ->lockForUpdate()
                ->first();
            if (! $claim) {
                throw new InvalidArgumentException('Warranty claim does not belong to this asset.');
            }
            $toState = (string) ($data['state'] ?? $claim->state);
            $this->warrantyClaimPolicy->assertTransition((string) $claim->state, $toState, $data);
            $replacementAssetId = ! empty($data['replacement_asset_public_id'])
                ? $this->references->requirePublicRecordId($companyId, 'tz_assets', (string) $data['replacement_asset_public_id'], 'replacement_asset_public_id')
                : $claim->replacement_asset_id;
            if ($replacementAssetId !== null && (int) $replacementAssetId === (int) $asset->id) {
                throw new InvalidArgumentException('A warranty replacement must be a different asset.');
            }
            $resolvedAt = in_array($toState, ['rejected', 'resolved'], true)
                ? ($data['resolved_at'] ?? now())
                : null;
            $this->db->table('tz_asset_warranty_claims')->where('id', $claim->id)->update([
                'state' => $toState,
                'outcome' => $data['outcome'] ?? $claim->outcome,
                'replacement_asset_id' => $replacementAssetId,
                'resolved_at' => $resolvedAt,
                'resolution_notes' => $data['resolution_notes'] ?? $claim->resolution_notes,
                'metadata' => array_key_exists('metadata', $data) ? $this->json($data['metadata']) : $claim->metadata,
                'updated_at' => now(),
            ]);
            return (array) $this->db->table('tz_asset_warranty_claims')->where('id', $claim->id)->first();
        }, 3);
    }

    public function registerIdentifier(string $assetPublicId, array $data, int $companyId, int $actorId): array
    {
        $asset = $this->asset($assetPublicId, $companyId);
        $type = (string) $data['identifier_type'];
        if (! in_array($type, ['qr', 'barcode', 'nfc', 'external'], true)) {
            throw new InvalidArgumentException('Unsupported asset identifier type.');
        }
        return $this->db->transaction(function () use ($asset, $data, $companyId, $type): array {
            if ((bool) ($data['is_primary'] ?? false)) {
                $this->db->table('tz_asset_identifiers')->where('company_id', $companyId)->where('asset_id', $asset->id)->where('identifier_type', $type)->update(['is_primary' => false, 'updated_at' => now()]);
            }
            return $this->insertIdentifier((int) $asset->id, $companyId, $type, (string) $data['value'], $data['metadata'] ?? [], (bool) ($data['is_primary'] ?? false));
        }, 3);
    }

    public function lookupIdentifier(string $value, ?string $type, int $companyId): ?array
    {
        $this->assertCompany($companyId);
        $query = $this->db->table('tz_asset_identifiers')->where('company_id', $companyId)->where('value', trim($value))->where('active', true);
        if ($type !== null && $type !== '') {
            $query->where('identifier_type', $type);
        }
        $identifier = $query->first();
        return $identifier ? $this->assetById((int) $identifier->asset_id) : null;
    }

    public function linkDocument(string $assetPublicId, array $data, int $companyId, int $actorId): array
    {
        $asset = $this->asset($assetPublicId, $companyId);
        $id = $this->db->table('tz_asset_document_links')->insertGetId([
            'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'asset_id' => $asset->id,
            'file_reference' => $data['file_reference'], 'document_type' => $data['document_type'],
            'related_type' => $data['related_type'] ?? null, 'related_public_id' => $data['related_public_id'] ?? null,
            'title' => $data['title'] ?? null, 'metadata' => $this->json($data['metadata'] ?? null),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_asset_document_links')->where('id', $id)->first();
    }

    public function createFromSupplyReceipt(array $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('Supply handoff requires idempotency_key.');
        }
        $existing = $this->db->table('tz_asset_supply_handoffs')->where('company_id', $companyId)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing?->asset_id) {
            return ['asset' => $this->assetById((int) $existing->asset_id), 'handoff' => (array) $existing, 'replayed' => true];
        }
        $durable = collect(['serialised', 'individually_tracked', 'long_term_assignment', 'maintained', 'calibrated', 'warrantied', 'depreciated'])
            ->contains(fn (string $key): bool => (bool) ($data[$key] ?? false));
        if (! $durable) {
            throw new InvalidArgumentException('Supply item does not meet durable asset criteria.');
        }
        $inventoryItemId = null;
        if (! empty($data['inventory_item_public_id'])) {
            $inventoryItemId = $this->references->requirePublicRecordId($companyId, 'tz_inventory_items', (string) $data['inventory_item_public_id'], 'inventory_item_public_id');
            $inventory = $this->db->table('tz_inventory_items')->where('id', $inventoryItemId)->first();
            if ($inventory && $inventory->supply_item_public_id && (string) $inventory->supply_item_public_id !== (string) $data['supply_item_public_id']) {
                throw new InvalidArgumentException('Inventory item does not match the supplied Titan Supply item.');
            }
        }

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $idempotencyKey, $inventoryItemId): array {
            $handoffId = $this->db->table('tz_asset_supply_handoffs')->insertGetId([
                'public_id' => (string) Str::ulid(), 'company_id' => $companyId,
                'idempotency_key' => $idempotencyKey, 'inventory_item_id' => $inventoryItemId,
                'supply_item_public_id' => $data['supply_item_public_id'],
                'supply_goods_receipt_item_public_id' => $data['supply_goods_receipt_item_public_id'] ?? null,
                'supply_purchase_order_public_id' => $data['supply_purchase_order_public_id'] ?? null,
                'supplier_public_id' => $data['supplier_public_id'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'source_payload_hash' => hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                'source_payload' => $this->json($data), 'status' => 'pending',
                'processed_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $asset = $this->createAsset([
                'category_public_id' => $data['category_public_id'] ?? null,
                'asset_number' => $data['asset_number'] ?? $data['asset_code'] ?? null,
                'name' => $data['name'], 'description' => $data['description'] ?? null,
                'serial_number' => $data['serial_number'] ?? null, 'manufacturer' => $data['manufacturer'] ?? null,
                'model' => $data['model'] ?? null, 'status' => 'received',
                'condition' => $data['condition'] ?? 'good', 'acquired_on' => $data['acquired_on'] ?? now()->toDateString(),
                'purchase_cost' => $data['purchase_cost'] ?? null, 'currency' => $data['currency'] ?? null,
                'supply_item_public_id' => $data['supply_item_public_id'],
                'supply_goods_receipt_item_public_id' => $data['supply_goods_receipt_item_public_id'] ?? null,
                'supply_purchase_order_public_id' => $data['supply_purchase_order_public_id'] ?? null,
                'supplier_public_id' => $data['supplier_public_id'] ?? null,
                'metadata' => ['supply_handoff_idempotency_key' => $idempotencyKey] + (array) ($data['metadata'] ?? []),
            ], $companyId, $actorId);
            $this->db->table('tz_asset_supply_handoffs')->where('id', $handoffId)->update([
                'asset_id' => $asset['id'], 'status' => 'completed', 'processed_at' => now(), 'updated_at' => now(),
            ]);
            return [
                'asset' => $asset,
                'handoff' => (array) $this->db->table('tz_asset_supply_handoffs')->where('id', $handoffId)->first(),
                'replayed' => false,
            ];
        }, 3);
    }

    public function availability(string $assetPublicId, int $companyId): array
    {
        $asset = $this->asset($assetPublicId, $companyId);
        $reasons = [];
        if (! $this->statusPolicy->isAssignable((string) $asset->status)) {
            $reasons[] = 'lifecycle_state_not_assignable';
        }
        if ($this->activeAssignment((int) $asset->id, $companyId)) {
            $reasons[] = 'active_custody_conflict';
        }
        if ($this->db->table('tz_asset_maintenance_records')->where('company_id', $companyId)->where('asset_id', $asset->id)->whereIn('status', ['due', 'scheduled', 'in_progress', 'awaiting_parts'])->exists()) {
            $reasons[] = 'maintenance_open';
        }
        if ($this->db->table('tz_asset_calibrations')->where('company_id', $companyId)->where('asset_id', $asset->id)->where('result', 'failed')->whereNull('return_to_service_approved_at')->exists()) {
            $reasons[] = 'failed_calibration';
        }
        return ['asset_public_id' => $assetPublicId, 'available' => $reasons === [], 'reasons' => $reasons, 'status' => $asset->status];
    }

    public function overdueCustody(int $companyId, array $filters = []): array
    {
        $this->assertCompany($companyId);
        $query = $this->db->table('tz_asset_assignments as assignments')
            ->join('tz_assets as assets', 'assets.id', '=', 'assignments.asset_id')
            ->where('assignments.company_id', $companyId)
            ->where('assignments.active', true)
            ->whereNotNull('assignments.expected_return_at')
            ->where('assignments.expected_return_at', '<', $filters['before'] ?? $filters['as_of'] ?? now());
        if (! empty($filters['worker_public_id'])) {
            $query->join('tz_workers as workers', 'workers.id', '=', 'assignments.worker_id')->where('workers.public_id', $filters['worker_public_id']);
        }
        if (! empty($filters['assignment_type'])) {
            $query->where('assignments.assignment_type', $filters['assignment_type']);
        }
        return $query->select(['assignments.*', 'assets.public_id as asset_public_id', 'assets.name as asset_name'])->orderBy('assignments.expected_return_at')->get()->map(fn ($row) => (array) $row)->all();
    }

    private function allocate(string $assetPublicId, array $data, int $companyId, int $actorId, bool $allowExisting): array
    {
        $this->assertCompany($companyId);
        return $this->db->transaction(function () use ($assetPublicId, $data, $companyId, $actorId, $allowExisting): array {
            $asset = $this->asset($assetPublicId, $companyId, true);
            return $this->allocateLocked($asset, $data, $companyId, $actorId, $allowExisting);
        }, 3);
    }

    private function allocateLocked(object $asset, array $data, int $companyId, int $actorId, bool $allowExisting): array
    {
        if (! empty($data['custodian_user_id'])) {
            $this->references->requireActiveMember($companyId, (int) $data['custodian_user_id'], 'custodian_user_id');
        }
        if (! empty($data['acknowledged_by_user_id'])) {
            $this->references->requireActiveMember($companyId, (int) $data['acknowledged_by_user_id'], 'acknowledged_by_user_id');
        }
        $active = $this->activeAssignment((int) $asset->id, $companyId, true);
        if ($active && ! $allowExisting) {
            throw new InvalidArgumentException('Asset has active custody. Active custody must be transferred or checked in first.');
        }
        $availability = $this->availability((string) $asset->public_id, $companyId);
        $availability['reasons'] = array_values(array_filter(
            $availability['reasons'],
            static fn (string $reason): bool => $reason !== 'active_custody_conflict' || $allowExisting,
        ));
        if ($availability['reasons'] !== []) {
            throw new InvalidArgumentException('Asset is unavailable: ' . implode(', ', $availability['reasons']) . '. Use the authorised status-override action before allocation.');
        }
        $type = $this->assignmentPolicy->assertTarget((string) $data['assignment_type'], $data);
        $target = $this->resolveTarget($type, $data, $companyId, (int) $asset->id);
        if ($target['worker_id'] !== null) {
            $this->qualificationPolicy->assertQualified(
                $companyId,
                (int) $target['worker_id'],
                isset($asset->category_id) ? (int) $asset->category_id : null,
                is_array($data['credential_requirements'] ?? null) ? $data['credential_requirements'] : [],
            );
        }
        $conditionOutId = null;
        if (! empty($data['condition_out'])) {
            $condition = $this->insertCondition((int) $asset->id, $companyId, $actorId, $data['condition_out'] + ['context' => 'checkout']);
            $conditionOutId = $condition['id'];
        }
        $id = $this->db->table('tz_asset_assignments')->insertGetId([
            'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'asset_id' => $asset->id,
            'premises_id' => $target['premises_id'], 'premises_zone_public_id' => $target['premises_zone_public_id'],
            'worker_id' => $target['worker_id'], 'team_public_id' => $target['team_public_id'],
            'vehicle_public_id' => $target['vehicle_public_id'], 'job_id' => $target['job_id'],
            'storage_location_id' => $target['storage_location_id'], 'target_asset_id' => $target['target_asset_id'],
            'assignment_type' => $type, 'allocation_mode' => $data['allocation_mode'] ?? 'assignment',
            'starts_at' => $data['starts_at'] ?? $data['checked_out_at'] ?? now(),
            'ends_at' => $data['ends_at'] ?? null, 'expected_return_at' => $data['expected_return_at'] ?? null,
            'active' => true, 'status' => 'active', 'active_lock' => 'active',
            'condition_out_id' => $conditionOutId, 'custodian_user_id' => $data['custodian_user_id'] ?? null,
            'issued_by_user_id' => $actorId, 'acknowledged_by_user_id' => $data['acknowledged_by_user_id'] ?? null,
            'acknowledged_at' => $data['acknowledged_at'] ?? null,
            'previous_assignment_id' => $data['previous_assignment_id'] ?? null,
            'reason' => $data['reason'] ?? null,
            'evidence_file_references' => $this->json($data['evidence_file_references'] ?? null),
            'metadata' => $this->json($data['metadata'] ?? null),
            'assigned_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($conditionOutId !== null) {
            $this->db->table('tz_asset_condition_records')->where('id', $conditionOutId)->update([
                'assignment_id' => $id,
                'updated_at' => now(),
            ]);
        }
        $this->applyCurrentTarget((int) $asset->id, $target, $actorId);
        $targetStatus = (string) ($data['target_state'] ?? 'assigned');
        if ((string) $asset->status !== $targetStatus) {
            $this->transitionLocked($asset, $targetStatus, ['reason' => $data['reason'] ?? 'Asset allocated'], $actorId);
        }
        return [
            'asset' => $this->assetById((int) $asset->id),
            'assignment' => (array) $this->db->table('tz_asset_assignments')->where('id', $id)->first(),
        ];
    }

    private function resolveTarget(string $type, array $data, int $companyId, int $assetId): array
    {
        $target = array_fill_keys(['premises_id', 'premises_zone_public_id', 'worker_id', 'team_public_id', 'vehicle_public_id', 'job_id', 'storage_location_id', 'target_asset_id'], null);
        if ($type === 'premise' || $type === 'premise_zone') {
            $target['premises_id'] = $this->references->requirePublicRecordId($companyId, 'tz_premises', (string) $data['premises_public_id'], 'premises_public_id');
            $target['premises_zone_public_id'] = $type === 'premise_zone' ? (string) $data['premise_zone_public_id'] : null;
        } elseif ($type === 'worker') {
            $target['worker_id'] = $this->references->requirePublicRecordId($companyId, 'tz_workers', (string) $data['worker_public_id'], 'worker_public_id');
        } elseif ($type === 'job') {
            $target['job_id'] = $this->references->requirePublicRecordId($companyId, 'tz_work_orders', (string) $data['job_public_id'], 'job_public_id');
        } elseif ($type === 'storage_location') {
            $target['storage_location_id'] = $this->references->requirePublicRecordId($companyId, 'tz_stock_locations', (string) $data['storage_location_public_id'], 'storage_location_public_id');
        } elseif ($type === 'asset') {
            $target['target_asset_id'] = $this->references->requirePublicRecordId($companyId, 'tz_assets', (string) $data['target_asset_public_id'], 'target_asset_public_id');
            if ($target['target_asset_id'] === $assetId) {
                throw new InvalidArgumentException('An asset cannot be allocated to itself.');
            }
        } elseif ($type === 'team') {
            $target['team_public_id'] = (string) $data['team_public_id'];
        } elseif ($type === 'vehicle') {
            $target['vehicle_public_id'] = (string) $data['vehicle_public_id'];
        }
        return $target;
    }

    private function applyCurrentTarget(int $assetId, array $target, int $actorId): void
    {
        $this->db->table('tz_assets')->where('id', $assetId)->update([
            'premises_id' => $target['premises_id'], 'premises_zone_public_id' => $target['premises_zone_public_id'],
            'custodian_worker_id' => $target['worker_id'], 'current_team_public_id' => $target['team_public_id'],
            'current_vehicle_public_id' => $target['vehicle_public_id'], 'current_job_id' => $target['job_id'],
            'storage_location_id' => $target['storage_location_id'], 'parent_asset_id' => $target['target_asset_id'],
            'updated_by_user_id' => $actorId, 'updated_at' => now(),
        ]);
    }

    private function clearCurrentTarget(int $assetId, int $actorId): void
    {
        $this->db->table('tz_assets')->where('id', $assetId)->update([
            'premises_id' => null, 'premises_zone_public_id' => null, 'custodian_worker_id' => null,
            'current_team_public_id' => null, 'current_vehicle_public_id' => null, 'current_job_id' => null,
            'storage_location_id' => null, 'parent_asset_id' => null,
            'updated_by_user_id' => $actorId, 'updated_at' => now(),
        ]);
    }

    private function transitionLocked(object $asset, string $toStatus, array $data, int $actorId): void
    {
        $from = (string) $asset->status;
        $approvedBy = isset($data['approved_by_user_id']) ? (int) $data['approved_by_user_id'] : null;
        if ($approvedBy !== null) {
            $this->references->requireActiveMember((int) $asset->company_id, $approvedBy, 'approved_by_user_id');
        }
        $override = (bool) ($data['override'] ?? false);
        $this->statusPolicy->assertTransition($from, $toStatus, $data['reason'] ?? null, $approvedBy, $override);
        $condition = (string) ($data['condition'] ?? $asset->condition);
        $updates = [
            'status' => $toStatus, 'condition' => $condition,
            'lock_version' => (int) ($asset->lock_version ?? 0) + 1,
            'updated_by_user_id' => $actorId, 'updated_at' => now(),
        ];
        if ($toStatus === 'retired') { $updates['retired_at'] = now(); }
        if ($toStatus === 'disposed') { $updates['disposed_at'] = now(); }
        if ($toStatus === 'written_off') { $updates['written_off_at'] = now(); }
        $this->db->table('tz_assets')->where('id', $asset->id)->update($updates);
        $this->appendStatusHistory($asset->company_id, (int) $asset->id, $from, $toStatus, (string) $asset->condition, $condition, $data['reason'] ?? null, $actorId, $approvedBy, $override, $data);
        $asset->status = $toStatus;
        $asset->condition = $condition;
        $asset->lock_version = $updates['lock_version'];
    }

    private function insertCondition(int $assetId, int $companyId, int $actorId, array $data): array
    {
        $grade = (string) ($data['grade'] ?? 'unknown');
        if (! in_array($grade, ['new', 'excellent', 'good', 'fair', 'poor', 'critical', 'unknown'], true)) {
            throw new InvalidArgumentException('Unsupported asset condition grade.');
        }
        $id = $this->db->table('tz_asset_condition_records')->insertGetId([
            'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'asset_id' => $assetId,
            'assignment_id' => $data['assignment_id'] ?? null, 'context' => $data['context'] ?? 'inspection',
            'grade' => $grade, 'summary' => $data['summary'] ?? null,
            'measurements' => $this->json($data['measurements'] ?? null),
            'evidence_file_references' => $this->json($data['evidence_file_references'] ?? null),
            'recorded_by_user_id' => $actorId, 'recorded_at' => $data['recorded_at'] ?? now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_asset_condition_records')->where('id', $id)->first();
    }

    private function insertIdentifier(int $assetId, int $companyId, string $type, string $value, array $metadata, bool $primary): array
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException('Identifier value cannot be empty.');
        }
        if ($this->db->table('tz_asset_identifiers')->where('company_id', $companyId)->where('identifier_type', $type)->where('value', $value)->exists()) {
            throw new InvalidArgumentException('Asset identifier already exists for this company.');
        }
        $id = $this->db->table('tz_asset_identifiers')->insertGetId([
            'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'asset_id' => $assetId,
            'identifier_type' => $type, 'value' => $value, 'is_primary' => $primary,
            'active' => true, 'metadata' => $this->json($metadata), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_asset_identifiers')->where('id', $id)->first();
    }

    private function applyMaintenanceCompletion(int $assetId, ?int $planId, array $data, int $actorId): void
    {
        $updates = ['last_service_at' => $data['completed_at'] ?? now(), 'next_service_due_on' => $data['next_due_on'] ?? null, 'updated_by_user_id' => $actorId, 'updated_at' => now()];
        if (in_array((string) ($data['maintenance_type'] ?? ''), ['inspection', 'test', 'audit'], true)) {
            $updates['last_inspection_at'] = $data['completed_at'] ?? now();
            $updates['next_inspection_due_on'] = $data['next_due_on'] ?? null;
        }
        $this->db->table('tz_assets')->where('id', $assetId)->update($updates);
        if ($planId !== null) {
            $plan = $this->db->table('tz_asset_maintenance_plans')->where('id', $planId)->first();
            $next = $data['next_due_on'] ?? ($plan && $plan->interval_days ? now()->addDays((int) $plan->interval_days) : null);
            $this->db->table('tz_asset_maintenance_plans')->where('id', $planId)->update([
                'next_due_at' => $next, 'last_completed_usage' => $data['completed_usage'] ?? $plan?->last_completed_usage, 'updated_at' => now(),
            ]);
        }
    }

    private function markUsagePlansDue(int $assetId, int $companyId, string $metric, float $reading, int $actorId): void
    {
        $plans = $this->db->table('tz_asset_maintenance_plans')->where('company_id', $companyId)->where('asset_id', $assetId)->where('active', true)->whereIn('interval_type', ['usage', 'hybrid'])->where('usage_metric', $metric)->get();
        foreach ($plans as $plan) {
            $threshold = (float) ($plan->last_completed_usage ?? 0) + (float) ($plan->usage_interval ?? 0);
            if ($reading + 0.000001 < $threshold || $this->db->table('tz_asset_maintenance_records')->where('company_id', $companyId)->where('asset_id', $assetId)->where('maintenance_plan_id', $plan->id)->whereIn('status', ['due', 'scheduled', 'in_progress', 'awaiting_parts'])->exists()) {
                continue;
            }
            $this->db->table('tz_asset_maintenance_records')->insert([
                'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'asset_id' => $assetId,
                'maintenance_plan_id' => $plan->id, 'maintenance_type' => $plan->calibration_required ? 'calibration' : 'preventive',
                'status' => 'due', 'priority' => 'normal', 'due_at' => now(),
                'notes' => "Usage threshold reached: {$reading} {$metric}", 'created_by_user_id' => $actorId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $asset = $this->db->table('tz_assets')->where('id', $assetId)->first();
            if ($asset && in_array((string) $asset->status, ['available', 'assigned', 'checked_out', 'in_use'], true)) {
                $this->transitionLocked($asset, 'maintenance_due', ['reason' => 'Usage maintenance threshold reached'], $actorId);
            }
        }
    }

    private function activeAssignment(int $assetId, int $companyId, bool $lock = false): ?object
    {
        $query = $this->db->table('tz_asset_assignments')->where('company_id', $companyId)->where('asset_id', $assetId)->where('active', true)->where('active_lock', 'active');
        if ($lock) { $query->lockForUpdate(); }
        return $query->first();
    }

    private function closeActiveAssignment(int $assetId, int $companyId, int $actorId, string $reason): void
    {
        $assignment = $this->activeAssignment($assetId, $companyId, true);
        if ($assignment) {
            $this->db->table('tz_asset_assignments')->where('id', $assignment->id)->update([
                'active' => false, 'active_lock' => null, 'status' => 'terminated',
                'ends_at' => now(), 'returned_at' => now(), 'returned_by_user_id' => $actorId,
                'reason' => $reason, 'updated_at' => now(),
            ]);
        }
    }

    private function maintenance(string $publicId, int $assetId, int $companyId, bool $lock = false): object
    {
        $query = $this->db->table('tz_asset_maintenance_records')->where('company_id', $companyId)->where('asset_id', $assetId)->where('public_id', $publicId);
        if ($lock) { $query->lockForUpdate(); }
        $record = $query->first();
        if (! $record) { throw new InvalidArgumentException('Maintenance record does not belong to this asset.'); }
        return $record;
    }

    private function asset(string $publicId, int $companyId, bool $lock = false): object
    {
        $this->assertCompany($companyId);
        $query = $this->db->table('tz_assets')->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at');
        if ($lock) { $query->lockForUpdate(); }
        $asset = $query->first();
        if (! $asset) { throw new InvalidArgumentException('Asset does not belong to the active company.'); }
        return $asset;
    }

    private function assetById(int $id): array
    {
        $row = $this->db->table('tz_assets')->where('id', $id)->first();
        return $row ? (array) $row : [];
    }

    private function appendStatusHistory(int $companyId, int $assetId, ?string $fromStatus, string $toStatus, ?string $fromCondition, ?string $toCondition, ?string $reason, int $actorId, ?int $approvedByUserId, bool $override, array $context): void
    {
        $this->db->table('tz_asset_status_history')->insert([
            'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'asset_id' => $assetId,
            'from_status' => $fromStatus, 'to_status' => $toStatus,
            'from_condition' => $fromCondition, 'to_condition' => $toCondition,
            'reason' => $reason, 'override_used' => $override,
            'changed_by_user_id' => $actorId, 'approved_by_user_id' => $approvedByUserId,
            'correlation_id' => $context['correlation_id'] ?? null,
            'evidence_file_references' => $this->json($context['evidence_file_references'] ?? null),
            'context' => $this->json($context), 'changed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function json(mixed $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}

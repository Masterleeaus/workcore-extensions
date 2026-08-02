<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Repairs\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Assets\Contracts\AssetRepositoryContract;
use App\Domains\WorkCore\System\Modules\Repairs\Contracts\RepairRepositoryContract;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentRepairRepository extends TenantScopedRepository implements RepairRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private AssetRepositoryContract $assets,
    ) {
        parent::__construct($db, $tenant);
    }

    public function search(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);

        return $this->db->table('tz_repair_orders as repairs')
            ->join('tz_assets as assets', static fn ($join) => $join
                ->on('assets.id', '=', 'repairs.asset_id')
                ->on('assets.company_id', '=', 'repairs.company_id'))
            ->leftJoin('tz_fleet_vehicles as vehicles', static fn ($join) => $join
                ->on('vehicles.id', '=', 'repairs.vehicle_id')
                ->on('vehicles.company_id', '=', 'repairs.company_id'))
            ->leftJoin('tz_asset_maintenance_records as maintenance', static fn ($join) => $join
                ->on('maintenance.id', '=', 'repairs.maintenance_record_id')
                ->on('maintenance.company_id', '=', 'repairs.company_id'))
            ->where('repairs.company_id', $companyId)
            ->whereNull('assets.deleted_at')
            ->when($filters['status'] ?? null, static fn ($query, $status) => $query->where('repairs.status', $status))
            ->when($filters['asset_public_id'] ?? null, static fn ($query, $asset) => $query->where('assets.public_id', $asset))
            ->select([
                'repairs.*',
                'assets.public_id as asset_public_id',
                'assets.name as asset_name',
                'vehicles.public_id as vehicle_public_id',
                'maintenance.public_id as maintenance_public_id',
            ])
            ->orderByDesc('repairs.id')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function createTemplate(array $data, int $companyId): array
    {
        $this->assertCompany($companyId);
        $publicId = (string) Str::ulid();
        $this->db->table('tz_repair_order_templates')->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'code' => $data['code'],
            'name' => $data['name'],
            'repair_type' => $data['repair_type'] ?? 'general',
            'default_tasks' => isset($data['default_tasks']) ? json_encode($data['default_tasks']) : null,
            'required_parts' => isset($data['required_parts']) ? json_encode($data['required_parts']) : null,
            'active' => $data['active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->byPublicId('tz_repair_order_templates', $companyId, $publicId);
    }

    public function report(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $asset = $this->record('tz_assets', (string) $data['asset_public_id'], $companyId, true);
        if (in_array((string) $asset->status, ['retired', 'disposed', 'written_off', 'lost'], true)) {
            throw new InvalidArgumentException('Terminal asset cannot receive a repair order.');
        }
        $vehicleId = null;
        if (! empty($data['vehicle_public_id'])) {
            $vehicle = $this->record('tz_fleet_vehicles', (string) $data['vehicle_public_id'], $companyId, true);
            if ((int) $vehicle->asset_id !== (int) $asset->id) {
                throw new InvalidArgumentException('Vehicle does not represent selected asset.');
            }
            $vehicleId = (int) $vehicle->id;
        }
        $templateId = ! empty($data['template_public_id'])
            ? (int) $this->record('tz_repair_order_templates', (string) $data['template_public_id'], $companyId, true)->id
            : null;
        $workOrderId = $this->optionalId('tz_work_orders', $data['work_order_public_id'] ?? null, $companyId);
        $workerId = $this->optionalId('tz_workers', $data['assigned_worker_public_id'] ?? null, $companyId);

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $asset, $vehicleId, $templateId, $workOrderId, $workerId): array {
            $maintenance = $this->assets->recordMaintenance((string) $asset->public_id, [
                'work_order_public_id' => $data['work_order_public_id'] ?? null,
                'worker_public_id' => $data['assigned_worker_public_id'] ?? null,
                'maintenance_type' => 'repair',
                'status' => 'scheduled',
                'reference' => $data['repair_number'],
                'priority' => $data['priority'] ?? 'normal',
                'scheduled_for' => $data['reported_at'] ?? now(),
                'findings' => $data['fault_description'],
                'cost' => $data['estimated_cost'] ?? null,
                'evidence_file_references' => $data['evidence_file_references'] ?? null,
                'metadata' => ['source' => 'workcore.repairs'],
            ], $companyId, $actorId);
            $publicId = (string) Str::ulid();
            $this->db->table('tz_repair_orders')->insert([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'asset_id' => $asset->id,
                'vehicle_id' => $vehicleId,
                'template_id' => $templateId,
                'work_order_id' => $workOrderId,
                'maintenance_record_id' => $maintenance['id'],
                'repair_number' => $data['repair_number'],
                'status' => 'reported',
                'priority' => $data['priority'] ?? 'normal',
                'fault_description' => $data['fault_description'],
                'estimated_cost' => $data['estimated_cost'] ?? null,
                'reported_at' => $data['reported_at'] ?? now(),
                'reported_by_user_id' => $actorId,
                'assigned_worker_id' => $workerId,
                'evidence_file_references' => isset($data['evidence_file_references']) ? json_encode($data['evidence_file_references']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if (($data['make_unavailable'] ?? true) === true && ! in_array((string) $asset->status, ['unavailable', 'under_maintenance'], true)) {
                $this->assets->changeStatus((string) $asset->public_id, [
                    'status' => 'unavailable',
                    'reason' => 'Repair reported: '.$data['fault_description'],
                ], $companyId, $actorId);
            }
            if ($vehicleId) {
                $this->db->table('tz_fleet_vehicles')->where('id', $vehicleId)->update(['status' => 'unavailable', 'updated_at' => now()]);
            }

            return $this->repair($companyId, $publicId);
        }, 3);
    }

    public function start(string $repairPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($repairPublicId, $data, $companyId, $actorId): array {
            $repair = $this->locked('tz_repair_orders', $repairPublicId, $companyId);
            if (! in_array((string) $repair->status, ['reported', 'approved', 'scheduled'], true)) {
                throw new InvalidArgumentException('Repair order cannot be started from its current state.');
            }
            $asset = $this->db->table('tz_assets')->where('company_id', $companyId)->where('id', $repair->asset_id)->whereNull('deleted_at')->first(['public_id']);
            $maintenance = $this->db->table('tz_asset_maintenance_records')->where('company_id', $companyId)->where('id', $repair->maintenance_record_id)->first(['public_id']);
            if (! $asset || ! $maintenance) {
                throw new InvalidArgumentException('Canonical asset maintenance record is unavailable.');
            }
            $workerId = $this->optionalId('tz_workers', $data['assigned_worker_public_id'] ?? null, $companyId) ?? $repair->assigned_worker_id;
            $this->assets->startMaintenance((string) $asset->public_id, (string) $maintenance->public_id, [
                'worker_public_id' => $data['assigned_worker_public_id'] ?? null,
                'started_at' => $data['started_at'] ?? now(),
                'downtime_started_at' => $data['started_at'] ?? now(),
                'reason' => $data['diagnosis'] ?? 'Repair work started',
            ], $companyId, $actorId);
            $this->db->table('tz_repair_orders')->where('id', $repair->id)->update([
                'status' => 'in_progress',
                'assigned_worker_id' => $workerId,
                'started_at' => $data['started_at'] ?? now(),
                'diagnosis' => $data['diagnosis'] ?? null,
                'updated_at' => now(),
            ]);
            if ($repair->vehicle_id) {
                $this->db->table('tz_fleet_vehicles')->where('id', $repair->vehicle_id)->update(['status' => 'under_maintenance', 'updated_at' => now()]);
            }

            return $this->repair($companyId, $repairPublicId);
        }, 3);
    }

    public function complete(string $repairPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        if (! ($data['return_to_service_approved'] ?? false)) {
            throw new InvalidArgumentException('Explicit return-to-service approval is required.');
        }

        return $this->db->transaction(function () use ($repairPublicId, $data, $companyId, $actorId): array {
            $repair = $this->locked('tz_repair_orders', $repairPublicId, $companyId);
            if ($repair->status !== 'in_progress') {
                throw new InvalidArgumentException('Only in-progress repairs may be completed.');
            }
            $asset = $this->db->table('tz_assets')->where('company_id', $companyId)->where('id', $repair->asset_id)->whereNull('deleted_at')->first(['public_id']);
            $maintenance = $this->db->table('tz_asset_maintenance_records')->where('company_id', $companyId)->where('id', $repair->maintenance_record_id)->first(['public_id']);
            if (! $asset || ! $maintenance) {
                throw new InvalidArgumentException('Canonical asset maintenance record is unavailable.');
            }
            $completed = $data['completed_at'] ?? now();
            $maintenanceRecord = $this->assets->completeMaintenance((string) $asset->public_id, (string) $maintenance->public_id, [
                'completed_at' => $completed,
                'downtime_ended_at' => $completed,
                'actions_taken' => $data['repair_actions'],
                'parts_references' => $data['parts_references'] ?? null,
                'cost' => $data['actual_cost'] ?? null,
                'evidence_file_references' => $data['evidence_file_references'] ?? null,
                'return_to_service_approved_by_user_id' => $actorId,
                'reason' => 'Repair completed and return-to-service approved',
            ], $companyId, $actorId);
            $this->db->table('tz_repair_orders')->where('id', $repair->id)->update([
                'status' => 'completed',
                'repair_actions' => $data['repair_actions'],
                'parts_references' => isset($data['parts_references']) ? json_encode($data['parts_references']) : null,
                'actual_cost' => $data['actual_cost'] ?? null,
                'completed_at' => $completed,
                'returned_to_service_at' => $completed,
                'return_approved_by_user_id' => $actorId,
                'evidence_file_references' => isset($data['evidence_file_references']) ? json_encode($data['evidence_file_references']) : $repair->evidence_file_references,
                'updated_at' => now(),
            ]);
            if ($repair->vehicle_id) {
                $assetStatus = $this->db->table('tz_assets')->where('company_id', $companyId)->where('id', $repair->asset_id)->value('status');
                $this->db->table('tz_fleet_vehicles')->where('id', $repair->vehicle_id)->update([
                    'status' => in_array($assetStatus, ['available', 'assigned'], true) ? $assetStatus : 'unavailable',
                    'updated_at' => now(),
                ]);
            }

            $record = $this->repair($companyId, $repairPublicId);
            $record['maintenance'] = $maintenanceRecord;

            return $record;
        }, 3);
    }

    private function repair(int $companyId, string $publicId): array
    {
        $row = $this->db->table('tz_repair_orders as repairs')
            ->join('tz_assets as assets', static fn ($join) => $join
                ->on('assets.id', '=', 'repairs.asset_id')
                ->on('assets.company_id', '=', 'repairs.company_id'))
            ->leftJoin('tz_fleet_vehicles as vehicles', static fn ($join) => $join
                ->on('vehicles.id', '=', 'repairs.vehicle_id')
                ->on('vehicles.company_id', '=', 'repairs.company_id'))
            ->leftJoin('tz_asset_maintenance_records as maintenance', static fn ($join) => $join
                ->on('maintenance.id', '=', 'repairs.maintenance_record_id')
                ->on('maintenance.company_id', '=', 'repairs.company_id'))
            ->where('repairs.company_id', $companyId)
            ->where('repairs.public_id', $publicId)
            ->select([
                'repairs.*',
                'assets.public_id as asset_public_id',
                'assets.name as asset_name',
                'vehicles.public_id as vehicle_public_id',
                'maintenance.public_id as maintenance_public_id',
            ])
            ->first();

        return $row ? (array) $row : ['public_id' => $publicId];
    }

    private function record(string $table, string $publicId, int $companyId, bool $soft = false): object
    {
        $this->assertCompany($companyId);
        $query = $this->db->table($table)->where('company_id', $companyId)->where('public_id', $publicId);
        if ($soft) {
            $query->whereNull('deleted_at');
        }
        $row = $query->first();
        if (! $row) {
            throw new InvalidArgumentException('Referenced record is unavailable in active company.');
        }

        return $row;
    }

    private function optionalId(string $table, mixed $publicId, int $companyId): ?int
    {
        return $publicId ? (int) $this->record($table, (string) $publicId, $companyId)->id : null;
    }

    private function locked(string $table, string $publicId, int $companyId): object
    {
        $this->assertCompany($companyId);
        $row = $this->db->table($table)->where('company_id', $companyId)->where('public_id', $publicId)->lockForUpdate()->first();
        if (! $row) {
            throw new InvalidArgumentException('Repair order is unavailable.');
        }

        return $row;
    }

    private function byPublicId(string $table, int $companyId, string $publicId): array
    {
        $row = $this->db->table($table)->where('company_id', $companyId)->where('public_id', $publicId)->first();

        return $row ? (array) $row : ['public_id' => $publicId];
    }

    private function assertActor(int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId !== $this->actorId()) {
            throw new \RuntimeException('Repository actor does not match active WorkCore actor.');
        }
    }
}

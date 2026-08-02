<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Fleet\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Assets\Contracts\AssetRepositoryContract;
use App\Domains\WorkCore\System\Modules\Fleet\Contracts\FleetRepositoryContract;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentFleetRepository extends TenantScopedRepository implements FleetRepositoryContract
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

        return $this->db->table('tz_fleet_vehicles as vehicles')
            ->join('tz_assets as assets', static fn ($join) => $join
                ->on('assets.id', '=', 'vehicles.asset_id')
                ->on('assets.company_id', '=', 'vehicles.company_id'))
            ->leftJoin('tz_workers as workers', static fn ($join) => $join
                ->on('workers.id', '=', 'assets.custodian_worker_id')
                ->on('workers.company_id', '=', 'vehicles.company_id'))
            ->where('vehicles.company_id', $companyId)
            ->whereNull('vehicles.deleted_at')
            ->whereNull('assets.deleted_at')
            ->when($filters['status'] ?? null, static fn ($query, $status) => $query->where('vehicles.status', $status))
            ->when($filters['q'] ?? null, static fn ($query, $term) => $query->where(static fn ($nested) => $nested
                ->where('vehicles.fleet_number', 'like', "%{$term}%")
                ->orWhere('vehicles.registration_number', 'like', "%{$term}%")
                ->orWhere('vehicles.vin', 'like', "%{$term}%")
                ->orWhere('assets.name', 'like', "%{$term}%")))
            ->select([
                'vehicles.*',
                'assets.public_id as asset_public_id',
                'assets.name as asset_name',
                'assets.status as asset_status',
                'workers.public_id as custodian_worker_public_id',
            ])
            ->selectRaw("CASE WHEN vehicles.registration_expires_on IS NOT NULL AND vehicles.registration_expires_on < CURRENT_DATE THEN 'expired' WHEN vehicles.registration_expires_on IS NOT NULL AND vehicles.registration_expires_on <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) THEN 'due_soon' ELSE 'current' END as registration_compliance")
            ->selectRaw("CASE WHEN vehicles.insurance_expires_on IS NOT NULL AND vehicles.insurance_expires_on < CURRENT_DATE THEN 'expired' WHEN vehicles.insurance_expires_on IS NOT NULL AND vehicles.insurance_expires_on <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) THEN 'due_soon' ELSE 'current' END as insurance_compliance")
            ->selectRaw("CASE WHEN (vehicles.next_service_due_on IS NOT NULL AND vehicles.next_service_due_on <= CURRENT_DATE) OR (vehicles.next_service_due_odometer IS NOT NULL AND vehicles.odometer >= vehicles.next_service_due_odometer) THEN 'due' ELSE 'current' END as service_compliance")
            ->orderBy('vehicles.fleet_number')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function createVehicle(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($data, $companyId, $actorId): array {
            $asset = $this->db->table('tz_assets')
                ->where('company_id', $companyId)
                ->where('public_id', (string) $data['asset_public_id'])
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();
            if (! $asset) {
                throw new InvalidArgumentException('Fleet asset is unavailable in the active company.');
            }
            if (in_array((string) $asset->status, ['retired', 'disposed', 'written_off', 'lost'], true)) {
                throw new InvalidArgumentException('Terminal asset cannot become a fleet vehicle.');
            }
            if ($this->db->table('tz_fleet_vehicles')
                ->where('company_id', $companyId)
                ->where('asset_id', $asset->id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->exists()) {
                throw new InvalidArgumentException('Asset already has an active fleet profile.');
            }

            $premisesId = ! empty($data['home_premises_public_id'])
                ? (int) $this->record('tz_premises', (string) $data['home_premises_public_id'], $companyId, true)->id
                : null;
            $initialStatus = $this->initialVehicleStatus($asset, $data);
            $publicId = (string) Str::ulid();
            $this->db->table('tz_fleet_vehicles')->insert([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'asset_id' => $asset->id,
                'home_premises_id' => $premisesId,
                'fleet_number' => $data['fleet_number'],
                'registration_number' => $data['registration_number'],
                'vin' => $data['vin'] ?? null,
                'make' => $data['make'] ?? $asset->manufacturer,
                'model' => $data['model'] ?? $asset->model,
                'year' => $data['year'] ?? null,
                'vehicle_type' => $data['vehicle_type'] ?? 'service_vehicle',
                'fuel_type' => $data['fuel_type'] ?? null,
                'status' => $initialStatus,
                'odometer' => $data['odometer'] ?? 0,
                'odometer_unit' => $data['odometer_unit'] ?? 'km',
                'registration_expires_on' => $data['registration_expires_on'] ?? null,
                'insurance_expires_on' => $data['insurance_expires_on'] ?? null,
                'next_service_due_on' => $data['next_service_due_on'] ?? null,
                'next_service_due_odometer' => $data['next_service_due_odometer'] ?? null,
                'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->db->table('tz_assets')->where('id', $asset->id)->update([
                'asset_type' => 'vehicle',
                'registration_number' => $data['registration_number'],
                'updated_by_user_id' => $actorId,
                'updated_at' => now(),
            ]);

            return $this->vehicle($companyId, $publicId);
        }, 3);
    }

    public function recordMileage(string $vehiclePublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($vehiclePublicId, $data, $companyId, $actorId): array {
            $vehicle = $this->locked('tz_fleet_vehicles', $vehiclePublicId, $companyId);
            $asset = $this->db->table('tz_assets')->where('company_id', $companyId)->where('id', $vehicle->asset_id)->whereNull('deleted_at')->first(['public_id']);
            if (! $asset) {
                throw new InvalidArgumentException('Fleet asset is unavailable.');
            }
            $end = (float) $data['odometer_end'];
            $start = isset($data['odometer_start']) ? (float) $data['odometer_start'] : (float) $vehicle->odometer;
            if ($end < $start || $end < (float) $vehicle->odometer) {
                throw new InvalidArgumentException('Odometer readings cannot move backwards.');
            }
            $workerId = $this->optionalId('tz_workers', $data['worker_public_id'] ?? null, $companyId);
            $workOrderId = $this->optionalId('tz_work_orders', $data['work_order_public_id'] ?? null, $companyId);
            $appointmentId = $this->optionalId('tz_appointments', $data['appointment_public_id'] ?? null, $companyId);
            $publicId = (string) Str::ulid();
            $loggedAt = $data['logged_at'] ?? now();
            $this->db->table('tz_fleet_mileage_logs')->insert([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'vehicle_id' => $vehicle->id,
                'worker_id' => $workerId,
                'work_order_id' => $workOrderId,
                'appointment_id' => $appointmentId,
                'odometer_start' => $start,
                'odometer_end' => $end,
                'distance' => $end - $start,
                'logged_at' => $loggedAt,
                'source' => $data['source'] ?? 'manual',
                'notes' => $data['notes'] ?? null,
                'evidence_file_references' => isset($data['evidence_file_references']) ? json_encode($data['evidence_file_references']) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $serviceDue = ($vehicle->next_service_due_odometer !== null && $end >= (float) $vehicle->next_service_due_odometer)
                || ($vehicle->next_service_due_on !== null && (string) $vehicle->next_service_due_on <= now()->toDateString());
            $this->db->table('tz_fleet_vehicles')->where('id', $vehicle->id)->update([
                'odometer' => $end,
                'status' => $serviceDue && $vehicle->status === 'available' ? 'maintenance_due' : $vehicle->status,
                'updated_at' => now(),
            ]);
            $this->assets->recordMeterReading((string) $asset->public_id, [
                'worker_public_id' => $data['worker_public_id'] ?? null,
                'reading_type' => 'odometer',
                'reading_value' => $end,
                'unit' => $vehicle->odometer_unit,
                'read_at' => $loggedAt,
                'source' => 'fleet',
                'notes' => $data['notes'] ?? null,
                'metadata' => ['fleet_vehicle_public_id' => $vehiclePublicId],
            ], $companyId, $actorId);

            return $this->byPublicId('tz_fleet_mileage_logs', $companyId, $publicId);
        }, 3);
    }

    public function assign(string $vehiclePublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($vehiclePublicId, $data, $companyId, $actorId): array {
            $vehicle = $this->locked('tz_fleet_vehicles', $vehiclePublicId, $companyId);
            $this->assertVehicleAssignable($vehicle);
            $active = $this->db->table('tz_fleet_assignments')
                ->where('company_id', $companyId)
                ->where('vehicle_id', $vehicle->id)
                ->where('active_lock', 'active')
                ->lockForUpdate()
                ->first();
            if ($active) {
                throw new InvalidArgumentException('Vehicle already has an active assignment.');
            }
            $workerId = $this->optionalId('tz_workers', $data['worker_public_id'] ?? null, $companyId);
            $workOrderId = $this->optionalId('tz_work_orders', $data['work_order_public_id'] ?? null, $companyId);
            $appointmentId = $this->optionalId('tz_appointments', $data['appointment_public_id'] ?? null, $companyId);
            if (! $workerId && ! $workOrderId && $appointmentId) {
                $appointment = $this->db->table('tz_appointments')->where('company_id', $companyId)->where('id', $appointmentId)->first(['work_order_id']);
                $workOrderId = $appointment?->work_order_id ? (int) $appointment->work_order_id : null;
            }
            if (! $workerId && ! $workOrderId) {
                throw new InvalidArgumentException('Vehicle assignment requires a worker or work order custody target.');
            }
            $asset = $this->db->table('tz_assets')->where('company_id', $companyId)->where('id', $vehicle->asset_id)->whereNull('deleted_at')->first(['public_id']);
            if (! $asset) {
                throw new InvalidArgumentException('Fleet asset is unavailable.');
            }
            $assetPayload = [
                'assignment_type' => $workerId ? 'worker' : 'job',
                'worker_public_id' => $data['worker_public_id'] ?? null,
                'job_public_id' => $workOrderId ? $this->publicIdFor('tz_work_orders', $workOrderId, $companyId) : null,
                'allocation_mode' => 'assignment',
                'starts_at' => $data['starts_at'] ?? now(),
                'expected_return_at' => $data['expected_return_at'] ?? null,
                'reason' => $data['notes'] ?? 'Fleet vehicle assigned',
                'metadata' => ['fleet_vehicle_public_id' => $vehiclePublicId, 'appointment_public_id' => $data['appointment_public_id'] ?? null],
            ];
            $assetAssignment = $this->assets->assignAsset((string) $asset->public_id, $assetPayload, $companyId, $actorId);
            $assetAssignmentId = (int) ($assetAssignment['assignment']['id'] ?? 0);
            if ($assetAssignmentId < 1) {
                throw new InvalidArgumentException('Canonical asset custody assignment was not created.');
            }
            $publicId = (string) Str::ulid();
            $this->db->table('tz_fleet_assignments')->insert([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'vehicle_id' => $vehicle->id,
                'asset_assignment_id' => $assetAssignmentId,
                'worker_id' => $workerId,
                'work_order_id' => $workOrderId,
                'appointment_id' => $appointmentId,
                'assignment_type' => $data['assignment_type'] ?? ($workerId ? 'worker' : 'job'),
                'starts_at' => $data['starts_at'] ?? now(),
                'expected_return_at' => $data['expected_return_at'] ?? null,
                'status' => 'active',
                'active_lock' => 'active',
                'assigned_by_user_id' => $actorId,
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->db->table('tz_fleet_vehicles')->where('id', $vehicle->id)->update(['status' => 'assigned', 'updated_at' => now()]);

            return $this->byPublicId('tz_fleet_assignments', $companyId, $publicId);
        }, 3);
    }

    public function returnVehicle(string $assignmentPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($assignmentPublicId, $data, $companyId, $actorId): array {
            $assignment = $this->locked('tz_fleet_assignments', $assignmentPublicId, $companyId);
            if ($assignment->status !== 'active') {
                throw new InvalidArgumentException('Fleet assignment is not active.');
            }
            $vehicle = $this->db->table('tz_fleet_vehicles')->where('company_id', $companyId)->where('id', $assignment->vehicle_id)->lockForUpdate()->first();
            $asset = $this->db->table('tz_assets')->where('company_id', $companyId)->where('id', $vehicle->asset_id)->whereNull('deleted_at')->first(['public_id']);
            if (! $asset) {
                throw new InvalidArgumentException('Fleet asset is unavailable.');
            }
            $activeAssetAssignment = $this->db->table('tz_asset_assignments')
                ->where('company_id', $companyId)
                ->where('asset_id', $vehicle->asset_id)
                ->where('active', true)
                ->lockForUpdate()
                ->first(['id']);
            if (! $activeAssetAssignment || (int) $activeAssetAssignment->id !== (int) $assignment->asset_assignment_id) {
                throw new InvalidArgumentException('Fleet custody does not match canonical asset custody.');
            }
            $targetStatus = $this->serviceDue($vehicle) ? 'maintenance_due' : 'available';
            $this->assets->checkinAsset((string) $asset->public_id, [
                'returned_at' => $data['returned_at'] ?? now(),
                'target_status' => $targetStatus,
                'reason' => $data['notes'] ?? 'Fleet vehicle returned',
            ], $companyId, $actorId);
            $this->db->table('tz_fleet_assignments')->where('id', $assignment->id)->update([
                'status' => 'returned',
                'active_lock' => null,
                'returned_at' => $data['returned_at'] ?? now(),
                'notes' => $data['notes'] ?? $assignment->notes,
                'updated_at' => now(),
            ]);
            $this->db->table('tz_fleet_vehicles')->where('id', $vehicle->id)->update(['status' => $targetStatus, 'updated_at' => now()]);

            return $this->byPublicId('tz_fleet_assignments', $companyId, $assignmentPublicId);
        }, 3);
    }

    private function initialVehicleStatus(object $asset, array $data): string
    {
        $assetStatus = (string) $asset->status;
        if ($assetStatus === 'under_maintenance') {
            return 'under_maintenance';
        }
        if ($assetStatus === 'maintenance_due') {
            return 'maintenance_due';
        }
        if (in_array($assetStatus, ['assigned', 'checked_out', 'in_use', 'damaged', 'unavailable', 'ordered'], true)) {
            return 'unavailable';
        }

        $today = now()->toDateString();
        if ((! empty($data['registration_expires_on']) && (string) $data['registration_expires_on'] < $today)
            || (! empty($data['insurance_expires_on']) && (string) $data['insurance_expires_on'] < $today)) {
            return 'unavailable';
        }
        if ((! empty($data['next_service_due_on']) && (string) $data['next_service_due_on'] <= $today)
            || (isset($data['next_service_due_odometer']) && (float) ($data['odometer'] ?? 0) >= (float) $data['next_service_due_odometer'])) {
            return 'maintenance_due';
        }

        return 'available';
    }

    private function assertVehicleAssignable(object $vehicle): void
    {
        if (! in_array((string) $vehicle->status, ['available'], true)) {
            throw new InvalidArgumentException('Vehicle is unavailable for assignment.');
        }
        $today = now()->toDateString();
        if (($vehicle->registration_expires_on !== null && (string) $vehicle->registration_expires_on < $today)
            || ($vehicle->insurance_expires_on !== null && (string) $vehicle->insurance_expires_on < $today)) {
            throw new InvalidArgumentException('Vehicle registration or insurance has expired.');
        }
        if ($this->serviceDue($vehicle)) {
            throw new InvalidArgumentException('Vehicle maintenance is due.');
        }
    }

    private function serviceDue(object $vehicle): bool
    {
        return ($vehicle->next_service_due_on !== null && (string) $vehicle->next_service_due_on <= now()->toDateString())
            || ($vehicle->next_service_due_odometer !== null && (float) $vehicle->odometer >= (float) $vehicle->next_service_due_odometer);
    }

    private function vehicle(int $companyId, string $publicId): array
    {
        $row = $this->db->table('tz_fleet_vehicles as vehicles')
            ->join('tz_assets as assets', static fn ($join) => $join
                ->on('assets.id', '=', 'vehicles.asset_id')
                ->on('assets.company_id', '=', 'vehicles.company_id'))
            ->where('vehicles.company_id', $companyId)
            ->where('vehicles.public_id', $publicId)
            ->select(['vehicles.*', 'assets.public_id as asset_public_id', 'assets.name as asset_name'])
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
            throw new InvalidArgumentException('Referenced record is unavailable in the active company.');
        }

        return $row;
    }

    private function optionalId(string $table, mixed $publicId, int $companyId): ?int
    {
        return $publicId ? (int) $this->record($table, (string) $publicId, $companyId)->id : null;
    }

    private function publicIdFor(string $table, int $id, int $companyId): string
    {
        $row = $this->db->table($table)->where('company_id', $companyId)->where('id', $id)->first(['public_id']);
        if (! $row) {
            throw new InvalidArgumentException('Referenced record is unavailable in the active company.');
        }

        return (string) $row->public_id;
    }

    private function locked(string $table, string $publicId, int $companyId): object
    {
        $this->assertCompany($companyId);
        $row = $this->db->table($table)->where('company_id', $companyId)->where('public_id', $publicId)->lockForUpdate()->first();
        if (! $row) {
            throw new InvalidArgumentException('Record is unavailable in active company.');
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

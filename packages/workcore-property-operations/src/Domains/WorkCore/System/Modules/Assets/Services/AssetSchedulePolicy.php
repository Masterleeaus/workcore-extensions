<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Services;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final class AssetSchedulePolicy
{
    private const ACTIVE_APPOINTMENT_STATUSES = ['tentative', 'scheduled', 'confirmed', 'in_progress'];
    private const SCHEDULABLE_ASSET_STATUSES = ['available', 'assigned', 'checked_out', 'in_use'];

    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param array<int,array<string,mixed>> $assets
     * @return array<int,array<string,mixed>>
     */
    public function normaliseAndAssert(
        array $assets,
        int $companyId,
        string $startsAt,
        string $endsAt,
        ?int $ignoreAppointmentId = null,
    ): array {
        usort($assets, static function ($left, $right): int {
            $leftId = is_array($left) ? (string) ($left['asset_public_id'] ?? '') : '';
            $rightId = is_array($right) ? (string) ($right['asset_public_id'] ?? '') : '';
            return $leftId <=> $rightId;
        });
        $normalised = [];
        $seen = [];
        foreach ($assets as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException("Appointment asset at index {$index} is invalid.");
            }
            $publicId = trim((string) ($item['asset_public_id'] ?? ''));
            if ($publicId === '') {
                throw new InvalidArgumentException("Appointment asset at index {$index} requires asset_public_id.");
            }
            if (isset($seen[$publicId])) {
                throw new InvalidArgumentException("Asset {$publicId} is duplicated in the appointment.");
            }
            $seen[$publicId] = true;

            $asset = $this->db->table('tz_assets')
                ->where('company_id', $companyId)
                ->where('public_id', $publicId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first(['id', 'public_id', 'name', 'status', 'next_service_due_on', 'next_calibration_due_on']);
            if (! $asset) {
                throw new InvalidArgumentException("Asset {$publicId} does not belong to the active company.");
            }
            if (! in_array((string) $asset->status, self::SCHEDULABLE_ASSET_STATUSES, true)) {
                throw new InvalidArgumentException("Asset {$publicId} cannot be scheduled while {$asset->status}.");
            }
            if ($asset->next_service_due_on !== null && (string) $asset->next_service_due_on <= substr($startsAt, 0, 10)) {
                throw new InvalidArgumentException("Asset {$publicId} has service due before this appointment.");
            }
            if ($asset->next_calibration_due_on !== null && (string) $asset->next_calibration_due_on <= substr($startsAt, 0, 10)) {
                throw new InvalidArgumentException("Asset {$publicId} has calibration due before this appointment.");
            }
            if ($this->hasMaintenanceConflict($companyId, (int) $asset->id, $startsAt, $endsAt)) {
                throw new InvalidArgumentException("Asset {$publicId} has maintenance or calibration blocking this appointment.");
            }
            if ($this->hasAppointmentConflict($companyId, (int) $asset->id, $startsAt, $endsAt, $ignoreAppointmentId)) {
                throw new InvalidArgumentException("Asset {$publicId} is already reserved for an overlapping appointment.");
            }

            $normalised[] = [
                'asset_id' => (int) $asset->id,
                'asset_public_id' => (string) $asset->public_id,
                'asset_name' => (string) $asset->name,
                'role' => trim((string) ($item['role'] ?? 'required')) ?: 'required',
                'is_required' => (bool) ($item['is_required'] ?? true),
                'notes' => isset($item['notes']) ? trim((string) $item['notes']) : null,
            ];
        }
        return $normalised;
    }

    private function hasAppointmentConflict(int $companyId, int $assetId, string $startsAt, string $endsAt, ?int $ignoreAppointmentId): bool
    {
        return $this->db->table('tz_appointment_assets as appointment_assets')
            ->join('tz_appointments as appointments', 'appointments.id', '=', 'appointment_assets.appointment_id')
            ->where('appointment_assets.company_id', $companyId)
            ->where('appointment_assets.asset_id', $assetId)
            ->where('appointments.company_id', $companyId)
            ->whereNull('appointments.deleted_at')
            ->whereIn('appointments.status', self::ACTIVE_APPOINTMENT_STATUSES)
            ->when($ignoreAppointmentId !== null, static fn ($query) => $query->where('appointments.id', '<>', $ignoreAppointmentId))
            ->where('appointments.starts_at', '<', $endsAt)
            ->where('appointments.ends_at', '>', $startsAt)
            ->exists();
    }

    private function hasMaintenanceConflict(int $companyId, int $assetId, string $startsAt, string $endsAt): bool
    {
        $maintenance = $this->db->table('tz_asset_maintenance_records')
            ->where('company_id', $companyId)
            ->where('asset_id', $assetId)
            ->where(static function ($query) use ($startsAt, $endsAt): void {
                $query->whereIn('status', ['due', 'in_progress', 'awaiting_parts'])
                    ->orWhere(static function ($window) use ($startsAt, $endsAt): void {
                        $window->where('status', 'scheduled')
                            ->where(static function ($scheduled) use ($startsAt, $endsAt): void {
                                $scheduled->whereNull('scheduled_for')
                                    ->orWhere(static function ($overlap) use ($startsAt, $endsAt): void {
                                        $overlap->where('scheduled_for', '<', $endsAt)
                                            ->where(static function ($end) use ($startsAt): void {
                                                $end->whereNull('completed_at')->orWhere('completed_at', '>', $startsAt);
                                            });
                                    });
                            });
                    });
            })
            ->exists();
        if ($maintenance) {
            return true;
        }

        return $this->db->table('tz_asset_calibrations')
            ->where('company_id', $companyId)
            ->where('asset_id', $assetId)
            ->where('result', 'failed')
            ->whereNull('return_to_service_approved_at')
            ->exists();
    }
}

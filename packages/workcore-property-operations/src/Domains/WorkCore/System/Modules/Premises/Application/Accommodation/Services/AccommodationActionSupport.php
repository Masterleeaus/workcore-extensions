<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Services;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final class AccommodationActionSupport
{
    public function __construct(private ConnectionInterface $db) {}

    public function property(int $companyId, string $publicId, bool $lock = false): object
    {
        $query = $this->db->table('pm_properties')->where('company_id', $companyId)->where('public_id', $publicId);
        if ($lock) $query->lockForUpdate();
        return $query->first() ?? throw new InvalidArgumentException('Accommodation property does not belong to the active company.');
    }

    public function reservation(int $companyId, string $publicId, bool $lock = false): object
    {
        $query = $this->db->table('pm_accommodation_reservations')
            ->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at');
        if ($lock) $query->lockForUpdate();
        return $query->first() ?? throw new InvalidArgumentException('Accommodation reservation does not belong to the active company.');
    }

    public function stay(int $companyId, string $publicId, bool $lock = false): object
    {
        $query = $this->db->table('pm_accommodation_stays')->where('company_id', $companyId)->where('public_id', $publicId);
        if ($lock) $query->lockForUpdate();
        return $query->first() ?? throw new InvalidArgumentException('Accommodation stay does not belong to the active company.');
    }

    public function housekeepingTask(int $companyId, string $publicId, bool $lock = false): object
    {
        $query = $this->db->table('pm_accommodation_housekeeping_tasks')
            ->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at');
        if ($lock) $query->lockForUpdate();
        return $query->first() ?? throw new InvalidArgumentException('Housekeeping task does not belong to the active company.');
    }

    public function ratePlan(int $companyId, ?string $publicId): ?object
    {
        if ($publicId === null || trim($publicId) === '') return null;
        return $this->db->table('pm_accommodation_rate_plans')
            ->where('company_id', $companyId)->where('public_id', $publicId)
            ->where('active', true)->whereNull('deleted_at')->first()
            ?? throw new InvalidArgumentException('Accommodation rate plan does not belong to the active company or is inactive.');
    }

    public function businessLineId(int $companyId, ?string $publicId): ?int
    {
        if ($publicId === null || trim($publicId) === '') return null;
        $record = $this->db->table('tz_business_lines as bl')
            ->join('tz_company_verticals as cv', 'cv.id', '=', 'bl.company_vertical_id')
            ->where('bl.company_id', $companyId)->where('bl.public_id', $publicId)
            ->where('bl.status', 'active')->where('cv.status', 'active')->whereNull('bl.deleted_at')
            ->first(['bl.id']);
        if ($record === null) throw new InvalidArgumentException('Business line does not belong to the active company or is inactive.');
        return (int) $record->id;
    }

    public function workerId(int $companyId, ?string $publicId): ?int
    {
        if ($publicId === null || trim($publicId) === '') return null;
        $id = $this->db->table('tz_workers')->where('company_id', $companyId)
            ->where('public_id', $publicId)->whereNull('deleted_at')->value('id');
        if ($id === null) throw new InvalidArgumentException('Worker does not belong to the active company.');
        return (int) $id;
    }

    /** @return array<string,mixed> */
    public function decode(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (! is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

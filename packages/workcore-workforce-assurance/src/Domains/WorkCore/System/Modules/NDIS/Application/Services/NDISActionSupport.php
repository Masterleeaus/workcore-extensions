<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Application\Services;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final class NDISActionSupport
{
    public function __construct(private ConnectionInterface $db) {}

    public function participant(int $companyId, string $publicId, bool $lock = false): object
    {
        return $this->record('tz_ndis_participants', $companyId, $publicId, 'NDIS participant', $lock, true);
    }

    public function contact(int $companyId, string $publicId, bool $lock = false): object
    {
        return $this->record('tz_ndis_participant_contacts', $companyId, $publicId, 'NDIS participant contact', $lock, true);
    }

    public function plan(int $companyId, string $publicId, bool $lock = false): object
    {
        return $this->record('tz_ndis_plans', $companyId, $publicId, 'NDIS plan', $lock, true);
    }

    public function goal(int $companyId, string $publicId, bool $lock = false): object
    {
        return $this->record('tz_ndis_participant_goals', $companyId, $publicId, 'NDIS participant goal', $lock, true);
    }

    public function agreement(int $companyId, string $publicId, bool $lock = false): object
    {
        return $this->record('tz_ndis_service_agreements', $companyId, $publicId, 'NDIS service agreement', $lock, true);
    }

    public function agreementItem(int $companyId, string $publicId, bool $lock = false): object
    {
        return $this->record('tz_ndis_service_agreement_items', $companyId, $publicId, 'NDIS service agreement item', $lock, true);
    }

    public function delivery(int $companyId, string $publicId, bool $lock = false): object
    {
        return $this->record('tz_ndis_support_deliveries', $companyId, $publicId, 'NDIS support delivery', $lock, true);
    }

    public function planBudget(int $companyId, string $publicId): object
    {
        return $this->record('tz_ndis_plan_budgets', $companyId, $publicId, 'NDIS plan budget', false, false);
    }

    public function optionalId(string $table, int $companyId, ?string $publicId, string $label, bool $softDeletes = true): ?int
    {
        $publicId = trim((string) $publicId);
        if ($publicId === '') return null;
        return (int) $this->record($table, $companyId, $publicId, $label, false, $softDeletes)->id;
    }

    public function customerId(int $companyId, ?string $publicId): ?int { return $this->optionalId('tz_customers', $companyId, $publicId, 'customer'); }
    public function customerContactId(int $companyId, ?string $publicId): ?int { return $this->optionalId('tz_customer_contacts', $companyId, $publicId, 'customer contact'); }
    public function partyRoleId(int $companyId, ?string $publicId): ?int { return $this->optionalId('pm_premise_party_roles', $companyId, $publicId, 'Premises party role', false); }
    public function occupancyId(int $companyId, ?string $publicId): ?int { return $this->optionalId('pm_premise_occupancies', $companyId, $publicId, 'Premises occupancy', false); }
    public function workerId(int $companyId, ?string $publicId): ?int { return $this->optionalId('tz_workers', $companyId, $publicId, 'worker'); }
    public function incidentId(int $companyId, ?string $publicId): ?int { return $this->optionalId('tz_safety_incidents', $companyId, $publicId, 'safety incident'); }
    public function appointmentId(int $companyId, ?string $publicId): ?int { return $this->optionalId('tz_appointments', $companyId, $publicId, 'appointment'); }
    public function workOrderId(int $companyId, ?string $publicId): ?int { return $this->optionalId('tz_work_orders', $companyId, $publicId, 'work order'); }
    public function premiseId(int $companyId, ?string $publicId): ?int { return $this->optionalId('pm_properties', $companyId, $publicId, 'premise'); }

    /** @return array<string,mixed> */
    public function decode(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (! is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function record(string $table, int $companyId, string $publicId, string $label, bool $lock, bool $softDeletes): object
    {
        $query = $this->db->table($table)->where('company_id', $companyId)->where('public_id', trim($publicId));
        if ($softDeletes) $query->whereNull('deleted_at');
        if ($lock) $query->lockForUpdate();
        return $query->first() ?? throw new InvalidArgumentException("{$label} does not belong to the active company.");
    }
}

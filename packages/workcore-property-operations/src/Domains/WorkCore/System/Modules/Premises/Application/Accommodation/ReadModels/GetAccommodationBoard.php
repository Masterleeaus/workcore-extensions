<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\ReadModels;

use App\Domains\WorkCore\System\Contracts\{PermissionResolverContract,TenantContextContract};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;

final class GetAccommodationBoard
{
    public function __construct(
        private TenantContextContract $tenant,
        private PermissionResolverContract $permissions,
        private ConnectionInterface $db,
    ) {}

    /** @return array<string,mixed> */
    public function execute(string $from, string $to, ?string $propertyPublicId = null): array
    {
        $companyId = (int) $this->tenant->companyId();
        if (! $this->permissions->allows(
            (int) $this->tenant->userId(), $companyId,
            (string) config('workcore.accommodation.permissions.view'),
        )) {
            throw new AuthorizationException('The actor is not permitted to view the accommodation board.');
        }

        $reservations = $this->db->table('pm_accommodation_reservations as r')
            ->join('pm_properties as p', function ($join): void {
                $join->on('p.id', '=', 'r.premise_id')->on('p.company_id', '=', 'r.company_id');
            })
            ->leftJoin('pm_premise_spaces as s', 's.id', '=', 'r.primary_space_id')
            ->where('r.company_id', $companyId)->whereNull('r.deleted_at')
            ->where('r.arrival_at', '<', $to)->where('r.departure_at', '>', $from)
            ->when($propertyPublicId, fn ($query) => $query->where('p.public_id', $propertyPublicId))
            ->orderBy('r.arrival_at')->limit(1000)
            ->get([
                'r.public_id', 'r.reservation_type', 'r.status', 'r.source_channel', 'r.external_reference',
                'r.primary_guest_name', 'r.arrival_at', 'r.departure_at', 'r.adults', 'r.children',
                'r.total_amount', 'r.balance_amount', 'r.currency',
                'p.public_id as property_public_id', 'p.name as property_name',
                's.public_id as space_public_id', 's.name as space_name',
            ])->map(static fn (object $row): array => (array) $row)->all();

        $stays = $this->db->table('pm_accommodation_stays as st')
            ->join('pm_accommodation_reservations as r', 'r.id', '=', 'st.reservation_id')
            ->join('pm_properties as p', 'p.id', '=', 'st.premise_id')
            ->join('pm_premise_spaces as s', 's.id', '=', 'st.space_id')
            ->where('st.company_id', $companyId)
            ->where(function ($query) use ($from, $to): void {
                $query->where(function ($window) use ($from, $to): void {
                    $window->where('st.expected_arrival_at', '<', $to)
                        ->where('st.expected_departure_at', '>', $from);
                })->orWhere('st.status', 'in_house');
            })
            ->when($propertyPublicId, fn ($query) => $query->where('p.public_id', $propertyPublicId))
            ->orderBy('st.expected_departure_at')->limit(1000)
            ->get([
                'st.public_id', 'st.status', 'st.expected_arrival_at', 'st.expected_departure_at',
                'st.checked_in_at', 'st.checked_out_at', 'r.public_id as reservation_public_id',
                'r.primary_guest_name', 'p.public_id as property_public_id', 'p.name as property_name',
                's.public_id as space_public_id', 's.name as space_name',
            ])->map(static fn (object $row): array => (array) $row)->all();

        $housekeeping = $this->db->table('pm_accommodation_housekeeping_tasks as h')
            ->join('pm_properties as p', 'p.id', '=', 'h.premise_id')
            ->join('pm_premise_spaces as s', 's.id', '=', 'h.space_id')
            ->leftJoin('tz_workers as w', 'w.id', '=', 'h.assigned_worker_id')
            ->where('h.company_id', $companyId)->whereNull('h.deleted_at')
            ->whereNotIn('h.status', ['ready', 'cancelled'])
            ->when($propertyPublicId, fn ($query) => $query->where('p.public_id', $propertyPublicId))
            ->orderByRaw("CASE h.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('h.due_at')->limit(1000)
            ->get([
                'h.public_id', 'h.task_type', 'h.status', 'h.priority', 'h.due_at', 'h.started_at',
                'p.public_id as property_public_id', 'p.name as property_name',
                's.public_id as space_public_id', 's.name as space_name',
                'w.public_id as assigned_worker_public_id', 'w.display_name as assigned_worker_name',
            ])->map(static fn (object $row): array => (array) $row)->all();

        return [
            'from' => $from,
            'to' => $to,
            'property_public_id' => $propertyPublicId,
            'reservations' => $reservations,
            'stays' => $stays,
            'housekeeping' => $housekeeping,
            'summary' => [
                'arrivals' => count(array_filter($reservations, static fn (array $r): bool => (string) $r['arrival_at'] >= $from && (string) $r['arrival_at'] < $to)),
                'departures' => count(array_filter($reservations, static fn (array $r): bool => (string) $r['departure_at'] >= $from && (string) $r['departure_at'] < $to)),
                'in_house' => count(array_filter($stays, static fn (array $s): bool => $s['status'] === 'in_house')),
                'housekeeping_open' => count($housekeeping),
            ],
        ];
    }
}

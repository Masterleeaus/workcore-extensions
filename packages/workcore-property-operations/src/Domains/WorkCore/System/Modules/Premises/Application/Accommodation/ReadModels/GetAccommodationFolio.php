<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\ReadModels;

use App\Domains\WorkCore\System\Contracts\{PermissionResolverContract,TenantContextContract};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final class GetAccommodationFolio
{
    public function __construct(
        private TenantContextContract $tenant,
        private PermissionResolverContract $permissions,
        private ConnectionInterface $db,
    ) {}

    /** @return array<string,mixed> */
    public function execute(string $reservationPublicId): array
    {
        $companyId = (int) $this->tenant->companyId();
        if (! $this->permissions->allows(
            (int) $this->tenant->userId(), $companyId,
            (string) config('workcore.accommodation.permissions.charges'),
        )) {
            throw new AuthorizationException('The actor is not permitted to view accommodation charges.');
        }
        $reservation = $this->db->table('pm_accommodation_reservations as r')
            ->join('pm_properties as p', 'p.id', '=', 'r.premise_id')
            ->where('r.company_id', $companyId)->where('r.public_id', $reservationPublicId)->whereNull('r.deleted_at')
            ->first([
                'r.id', 'r.public_id', 'r.primary_guest_name', 'r.status', 'r.arrival_at', 'r.departure_at',
                'r.currency', 'r.subtotal_amount', 'r.tax_amount', 'r.total_amount', 'r.deposit_amount', 'r.balance_amount',
                'p.public_id as property_public_id', 'p.name as property_name',
            ]);
        if ($reservation === null) throw new InvalidArgumentException('Accommodation reservation does not belong to the active company.');

        $charges = $this->db->table('pm_accommodation_charges')
            ->where('company_id', $companyId)->where('reservation_id', (int) $reservation->id)
            ->orderBy('occurred_at')->orderBy('id')
            ->get([
                'public_id', 'charge_type', 'description', 'quantity', 'unit_amount', 'tax_amount',
                'total_amount', 'currency', 'status', 'occurred_at', 'posted_at',
                'finance_provider', 'finance_external_id', 'finance_reference',
            ])->map(static fn (object $row): array => (array) $row)->all();

        $posted = array_values(array_filter($charges, static fn (array $charge): bool => $charge['status'] === 'posted'));
        $chargeTotal = array_sum(array_map(static fn (array $charge): float => (float) $charge['total_amount'], $posted));
        $chargeTax = array_sum(array_map(static fn (array $charge): float => (float) $charge['tax_amount'], $posted));
        $baseTotal = (float) $reservation->total_amount;
        $deposit = (float) $reservation->deposit_amount;

        return [
            'reservation' => [
                'public_id' => (string) $reservation->public_id,
                'primary_guest_name' => (string) $reservation->primary_guest_name,
                'status' => (string) $reservation->status,
                'arrival_at' => (string) $reservation->arrival_at,
                'departure_at' => (string) $reservation->departure_at,
                'property_public_id' => (string) $reservation->property_public_id,
                'property_name' => (string) $reservation->property_name,
                'currency' => (string) $reservation->currency,
            ],
            'charges' => $charges,
            'summary' => [
                'base_subtotal' => (float) $reservation->subtotal_amount,
                'base_tax' => (float) $reservation->tax_amount,
                'base_total' => $baseTotal,
                'posted_charge_tax' => $chargeTax,
                'posted_charge_total' => $chargeTotal,
                'deposit_amount' => $deposit,
                'folio_total' => $baseTotal + $chargeTotal,
                'balance_due' => $baseTotal + $chargeTotal - $deposit,
                'currency' => (string) $reservation->currency,
            ],
        ];
    }
}

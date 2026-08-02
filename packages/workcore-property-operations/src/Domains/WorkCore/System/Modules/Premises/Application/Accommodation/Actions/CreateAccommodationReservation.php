<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Services\{AccommodationActionSupport,AccommodationAvailabilityService};
use App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation\{AccommodationDateWindow,AccommodationRateCalculator,AccommodationReservationState};
use App\Domains\WorkCore\System\References\TypedReference;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CreateAccommodationReservation implements BusinessActionHandlerContract
{
    public function __construct(
        private ConnectionInterface $db,
        private AccommodationActionSupport $records,
        private AccommodationAvailabilityService $availability,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $payload = $request->payload;
        $status = (string) ($payload['status'] ?? 'confirmed');
        if (! in_array($status, ['draft', 'tentative', 'confirmed'], true)) {
            throw new InvalidArgumentException('A reservation must begin as draft, tentative or confirmed.');
        }
        $arrivalAt = (string) $payload['arrival_at'];
        $departureAt = (string) $payload['departure_at'];
        $nights = AccommodationDateWindow::nights($arrivalAt, $departureAt);
        if ($nights < 1) throw new DomainException('Accommodation reservation must include at least one night.');

        $spacePublicIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id),
            (array) ($payload['space_public_ids'] ?? []),
        ))));
        $guestCapacity = max(1, (int) ($payload['capacity'] ?? ((int) ($payload['adults'] ?? 1) + (int) ($payload['children'] ?? 0))));
        $propertyPublicId = (string) $payload['property_public_id'];

        $result = $this->db->transaction(function () use (
            $request, $payload, $status, $arrivalAt, $departureAt, $nights,
            $spacePublicIds, $guestCapacity, $propertyPublicId,
        ): array {
            $property = $this->records->property($request->companyId, $propertyPublicId, true);
            $available = $this->availability->assertSpacesAvailable(
                $request->companyId, $propertyPublicId, $spacePublicIds,
                $arrivalAt, $departureAt, 1,
            );
            $availableByPublicId = [];
            foreach ($available as $item) $availableByPublicId[(string) $item['space_public_id']] = $item;

            $spaces = $this->db->table('pm_premise_spaces')
                ->where('company_id', $request->companyId)
                ->where('premise_id', (int) $property->id)
                ->whereIn('public_id', $spacePublicIds)
                ->lockForUpdate()->get();
            if ($spaces->count() !== count($spacePublicIds)) {
                throw new DomainException('One or more accommodation spaces are invalid.');
            }
            $combinedCapacity = (int) $spaces->sum(static fn (object $space): int => max(1, (int) ($space->capacity ?? 1)));
            if ($combinedCapacity < $guestCapacity) {
                throw new DomainException('Selected accommodation spaces do not have enough combined capacity.');
            }

            $ratePlan = $this->records->ratePlan($request->companyId, $payload['rate_plan_public_id'] ?? null);
            if ($ratePlan !== null && $ratePlan->premise_id !== null && (int) $ratePlan->premise_id !== (int) $property->id) {
                throw new DomainException('Rate plan does not apply to the selected property.');
            }
            if ($ratePlan !== null && ($nights < (int) $ratePlan->minimum_nights || ($ratePlan->maximum_nights !== null && $nights > (int) $ratePlan->maximum_nights))) {
                throw new DomainException('Reservation length is outside the selected rate plan limits.');
            }

            $businessLineId = $this->records->businessLineId($request->companyId, $payload['business_line_public_id'] ?? null);
            $currency = strtoupper((string) ($payload['currency'] ?? ($ratePlan->currency ?? 'AUD')));
            $spaceAmounts = (array) ($payload['space_amounts'] ?? []);
            if ($spaceAmounts !== [] && count($spaceAmounts) !== $spaces->count()) {
                throw new DomainException('Explicit accommodation space amounts must be supplied for every selected space.');
            }
            $defaultAmounts = array_fill(0, $spaces->count(), 0.0);
            if ($ratePlan !== null && $spaceAmounts === []) {
                $defaultAmounts = AccommodationRateCalculator::distribute(
                    AccommodationRateCalculator::total(
                        (float) $ratePlan->base_amount,
                        (string) $ratePlan->pricing_basis,
                        $nights,
                        $spaces->count(),
                        $guestCapacity,
                    ),
                    $spaces->count(),
                );
            }
            $spaceRows = [];
            $calculatedSubtotal = 0.0;
            foreach ($spaces->values() as $index => $space) {
                $amount = array_key_exists((string) $space->public_id, $spaceAmounts)
                    ? (float) $spaceAmounts[(string) $space->public_id]
                    : (float) $defaultAmounts[$index];
                if ($amount < 0) {
                    throw new DomainException('Accommodation space amounts cannot be negative.');
                }
                $calculatedSubtotal += $amount;
                $spaceRows[] = ['record' => $space, 'amount' => $amount];
            }
            $calculatedSubtotal = round($calculatedSubtotal, 4);
            $subtotal = array_key_exists('subtotal_amount', $payload) ? (float) $payload['subtotal_amount'] : $calculatedSubtotal;
            $tax = (float) ($payload['tax_amount'] ?? 0);
            $total = array_key_exists('total_amount', $payload) ? (float) $payload['total_amount'] : $subtotal + $tax;
            $deposit = (float) ($payload['deposit_amount'] ?? 0);
            if (
                $subtotal < 0 || $tax < 0 || $total < 0 || $deposit < 0 || $deposit > $total
                || abs($subtotal - $calculatedSubtotal) > 0.01
                || abs($total - ($subtotal + $tax)) > 0.01
            ) {
                throw new DomainException('Accommodation reservation monetary totals are invalid or inconsistent.');
            }

            $publicId = (string) Str::ulid();
            $now = now();
            $reservationId = (int) $this->db->table('pm_accommodation_reservations')->insertGetId([
                'public_id' => $publicId,
                'company_id' => $request->companyId,
                'user_id' => $request->actorId,
                'premise_id' => (int) $property->id,
                'primary_space_id' => (int) $spaces->first()->id,
                'occupancy_id' => null,
                'agreement_id' => $this->agreementId($request->companyId, $payload['agreement_public_id'] ?? null),
                'business_line_id' => $businessLineId,
                'rate_plan_id' => $ratePlan?->id,
                'reservation_type' => (string) ($payload['reservation_type'] ?? 'short_stay'),
                'status' => $status,
                'source_channel' => (string) ($payload['source_channel'] ?? 'direct'),
                'external_reference' => $this->nullableString($payload['external_reference'] ?? null),
                'primary_guest_name' => trim((string) $payload['primary_guest_name']),
                'primary_guest_email' => $this->nullableString($payload['primary_guest_email'] ?? null),
                'primary_guest_phone' => $this->nullableString($payload['primary_guest_phone'] ?? null),
                'arrival_at' => $arrivalAt,
                'departure_at' => $departureAt,
                'booked_at' => $payload['booked_at'] ?? $now,
                'confirmed_at' => $status === 'confirmed' ? $now : null,
                'adults' => max(0, (int) ($payload['adults'] ?? 1)),
                'children' => max(0, (int) ($payload['children'] ?? 0)),
                'capacity' => $guestCapacity,
                'currency' => $currency,
                'subtotal_amount' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'deposit_amount' => $deposit,
                'balance_amount' => $total - $deposit,
                'notes' => $payload['notes'] ?? null,
                'metadata' => json_encode((array) ($payload['metadata'] ?? []), JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($spaceRows as $spaceRow) {
                $space = $spaceRow['record'];
                $amount = (float) $spaceRow['amount'];
                $this->db->table('pm_accommodation_reservation_spaces')->insert([
                    'company_id' => $request->companyId,
                    'reservation_id' => $reservationId,
                    'space_id' => (int) $space->id,
                    'rate_plan_id' => $ratePlan?->id,
                    'arrival_at' => $arrivalAt,
                    'departure_at' => $departureAt,
                    'capacity' => max(1, (int) ($space->capacity ?? 1)),
                    'unit_amount' => $amount,
                    'tax_amount' => 0,
                    'total_amount' => $amount,
                    'status' => AccommodationReservationState::isCapacityConsuming($status) ? 'reserved' : 'draft',
                    'metadata' => json_encode([], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $guests = (array) ($payload['guests'] ?? []);
            if ($guests === []) {
                $guests = [[
                    'guest_type' => $this->defaultGuestType((string) ($payload['reservation_type'] ?? 'short_stay')),
                    'display_name' => (string) $payload['primary_guest_name'],
                    'email' => $payload['primary_guest_email'] ?? null,
                    'phone' => $payload['primary_guest_phone'] ?? null,
                    'is_primary' => true,
                ]];
            }
            $primaryAssigned = false;
            foreach ($guests as $index => $guest) {
                $isPrimary = (bool) ($guest['is_primary'] ?? ($index === 0 && ! $primaryAssigned));
                if ($isPrimary) $primaryAssigned = true;
                $this->db->table('pm_accommodation_guests')->insert([
                    'public_id' => (string) Str::ulid(),
                    'company_id' => $request->companyId,
                    'reservation_id' => $reservationId,
                    'guest_type' => (string) ($guest['guest_type'] ?? 'guest'),
                    'display_name' => trim((string) ($guest['display_name'] ?? $payload['primary_guest_name'])),
                    'email' => $this->nullableString($guest['email'] ?? null),
                    'phone' => $this->nullableString($guest['phone'] ?? null),
                    'date_of_birth' => $guest['date_of_birth'] ?? null,
                    'is_primary' => $isPrimary,
                    'accessibility_requirements' => $guest['accessibility_requirements'] ?? null,
                    'emergency_contact' => json_encode((array) ($guest['emergency_contact'] ?? []), JSON_THROW_ON_ERROR),
                    'metadata' => json_encode((array) ($guest['metadata'] ?? []), JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $externalReference = $this->nullableString($payload['external_reference'] ?? null);
            if ($externalReference !== null) {
                $this->db->table('pm_accommodation_channel_references')->insert([
                    'public_id' => (string) Str::ulid(),
                    'company_id' => $request->companyId,
                    'premise_id' => (int) $property->id,
                    'space_id' => (int) $spaces->first()->id,
                    'reservation_id' => $reservationId,
                    'channel' => (string) ($payload['source_channel'] ?? 'direct'),
                    'entity_type' => 'reservation',
                    'external_id' => $externalReference,
                    'external_url' => $payload['external_url'] ?? null,
                    'status' => 'active',
                    'metadata' => json_encode([], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return $this->reservationSnapshot($request->companyId, $publicId);
        }, 3);

        return new ActionHandlerResult(
            data: $result,
            aggregate: new TypedReference('accommodation_reservation', (string) $result['public_id']),
            events: [new PendingDomainEvent('workcore.accommodation.reservation.created', 1, ['reservation' => $result])],
        );
    }

    private function agreementId(int $companyId, mixed $publicId): ?int
    {
        $publicId = $this->nullableString($publicId);
        if ($publicId === null) return null;
        $id = $this->db->table('pm_premise_agreements')->where('company_id', $companyId)->where('public_id', $publicId)->value('id');
        if ($id === null) throw new InvalidArgumentException('Agreement does not belong to the active company.');
        return (int) $id;
    }

    /** @return array<string,mixed> */
    private function reservationSnapshot(int $companyId, string $publicId): array
    {
        $record = (array) $this->db->table('pm_accommodation_reservations as r')
            ->join('pm_properties as p', 'p.id', '=', 'r.premise_id')
            ->where('r.company_id', $companyId)->where('r.public_id', $publicId)
            ->first(['r.*', 'p.public_id as property_public_id', 'p.name as property_name']);
        unset($record['id'], $record['company_id'], $record['premise_id'], $record['primary_space_id'], $record['occupancy_id'], $record['agreement_id'], $record['business_line_id'], $record['rate_plan_id'], $record['user_id']);
        $record['metadata'] = $this->records->decode($record['metadata'] ?? null);
        $record['spaces'] = $this->db->table('pm_accommodation_reservation_spaces as rs')
            ->join('pm_premise_spaces as s', 's.id', '=', 'rs.space_id')
            ->where('rs.company_id', $companyId)->where('rs.reservation_id', function ($q) use ($companyId, $publicId): void {
                $q->select('id')->from('pm_accommodation_reservations')->where('company_id', $companyId)->where('public_id', $publicId)->limit(1);
            })->get(['s.public_id as space_public_id', 's.name as space_name', 'rs.arrival_at', 'rs.departure_at', 'rs.status', 'rs.total_amount'])
            ->map(static fn (object $row): array => (array) $row)->all();
        $record['guests'] = $this->db->table('pm_accommodation_guests as g')
            ->join('pm_accommodation_reservations as r', 'r.id', '=', 'g.reservation_id')
            ->where('g.company_id', $companyId)->where('r.public_id', $publicId)->whereNull('g.deleted_at')
            ->get(['g.public_id', 'g.guest_type', 'g.display_name', 'g.email', 'g.phone', 'g.is_primary'])
            ->map(static fn (object $row): array => (array) $row)->all();
        return $record;
    }

    private function defaultGuestType(string $reservationType): string
    {
        return match ($reservationType) {
            'student' => 'student', 'rooming_house' => 'resident',
            'ndis_sta', 'ndis_mta' => 'participant', default => 'guest',
        };
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}

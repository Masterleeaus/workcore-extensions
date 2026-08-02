<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Services;

use Illuminate\Database\ConnectionInterface;

final class AccommodationOccupancyBridge
{
    public function __construct(private ConnectionInterface $db) {}

    /** @return array{occupancy_id:int,party_role_id:int,access_authorisation_id:int} */
    public function ensureForReservation(int $companyId, int $actorId, object $reservation, object $space, object $guest): array
    {
        $partyRoleId = $this->ensurePartyRole($companyId, $actorId, $reservation, $space, $guest);
        $occupancyId = $this->matchingOccupancyId($companyId, $reservation, $space, $guest);

        if ($occupancyId === null) {
            $occupancyId = (int) $this->db->table('pm_premise_occupancies')->insertGetId([
                'company_id' => $companyId,
                'user_id' => $actorId,
                'premise_id' => (int) $reservation->premise_id,
                'space_id' => (int) $space->id,
                'party_role_id' => $partyRoleId,
                'party_type' => 'snapshot',
                'party_id' => null,
                'display_name' => (string) $guest->display_name,
                'occupancy_type' => $this->occupancyType((string) $reservation->reservation_type),
                'status' => 'active',
                'start_date' => substr((string) $reservation->arrival_at, 0, 10),
                'end_date' => substr((string) $reservation->departure_at, 0, 10),
                'capacity_used' => 1,
                'is_primary' => (bool) ($guest->is_primary ?? false),
                'move_in_status' => 'checked_in',
                'metadata' => json_encode([
                    'accommodation_managed' => true,
                    'accommodation_reservation_id' => (int) $reservation->id,
                    'accommodation_guest_id' => (int) $guest->id,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $accessAuthorisationId = $this->ensureAccessAuthorisation(
            $companyId,
            $actorId,
            $reservation,
            $space,
            $guest,
            $partyRoleId,
        );

        $this->db->table('pm_accommodation_guests')->where('id', (int) $guest->id)->update([
            'party_role_id' => $partyRoleId,
            'occupancy_id' => $occupancyId,
            'updated_at' => now(),
        ]);
        $this->db->table('pm_accommodation_reservations')
            ->where('id', (int) $reservation->id)
            ->whereNull('occupancy_id')
            ->update(['occupancy_id' => $occupancyId, 'updated_at' => now()]);

        return [
            'occupancy_id' => $occupancyId,
            'party_role_id' => $partyRoleId,
            'access_authorisation_id' => $accessAuthorisationId,
        ];
    }

    public function vacateManagedOccupancy(int $companyId, ?int $occupancyId, ?int $accessAuthorisationId = null): void
    {
        if ($occupancyId !== null) {
            $occupancy = $this->db->table('pm_premise_occupancies')
                ->where('company_id', $companyId)->where('id', $occupancyId)->lockForUpdate()->first();
            if ($occupancy !== null) {
                $metadata = $this->decode($occupancy->metadata ?? null);
                if (($metadata['accommodation_managed'] ?? false) === true) {
                    $this->db->table('pm_premise_occupancies')->where('id', $occupancyId)->update([
                        'status' => 'vacated',
                        'actual_end_date' => now()->toDateString(),
                        'move_out_status' => 'checked_out',
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if ($accessAuthorisationId !== null) {
            $this->db->table('pm_premise_access_authorisations')
                ->where('company_id', $companyId)
                ->where('id', $accessAuthorisationId)
                ->where('status', 'active')
                ->update([
                    'status' => 'expired',
                    'ends_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    private function ensurePartyRole(int $companyId, int $actorId, object $reservation, object $space, object $guest): int
    {
        if ($guest->party_role_id !== null) {
            $partyRole = $this->db->table('pm_premise_party_roles')
                ->where('company_id', $companyId)
                ->where('id', (int) $guest->party_role_id)
                ->first();
            if ($partyRole !== null) {
                return (int) $partyRole->id;
            }
        }

        $source = $this->db->table('pm_premise_party_roles')
            ->where('company_id', $companyId)
            ->where('source_type', 'accommodation_guest')
            ->where('source_id', (int) $guest->id)
            ->first();
        if ($source !== null) {
            return (int) $source->id;
        }

        return (int) $this->db->table('pm_premise_party_roles')->insertGetId([
            'company_id' => $companyId,
            'user_id' => $actorId,
            'premise_id' => (int) $reservation->premise_id,
            'space_id' => (int) $space->id,
            'party_type' => 'snapshot',
            'party_id' => null,
            'role' => $this->partyRole((string) $reservation->reservation_type),
            'display_name' => (string) $guest->display_name,
            'email' => $guest->email,
            'phone' => $guest->phone,
            'valid_from' => substr((string) $reservation->arrival_at, 0, 10),
            'valid_until' => substr((string) $reservation->departure_at, 0, 10),
            'is_primary' => (bool) ($guest->is_primary ?? false),
            'permissions' => json_encode(['accommodation_access' => true], JSON_THROW_ON_ERROR),
            'source_type' => 'accommodation_guest',
            'source_id' => (int) $guest->id,
            'metadata' => json_encode(['reservation_id' => (int) $reservation->id], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function matchingOccupancyId(int $companyId, object $reservation, object $space, object $guest): ?int
    {
        $candidateOccupancyIds = array_values(array_unique(array_filter([
            $guest->occupancy_id !== null ? (int) $guest->occupancy_id : null,
            $reservation->occupancy_id !== null && (bool) ($guest->is_primary ?? false)
                ? (int) $reservation->occupancy_id
                : null,
        ])));
        if ($candidateOccupancyIds === []) {
            return null;
        }

        $existing = $this->db->table('pm_premise_occupancies')
            ->where('company_id', $companyId)
            ->whereIn('id', $candidateOccupancyIds)
            ->where('space_id', (int) $space->id)
            ->whereIn('status', ['reserved', 'pending', 'active', 'notice_given', 'ending', 'suspended'])
            ->orderByDesc('id')
            ->first();

        return $existing === null ? null : (int) $existing->id;
    }

    private function ensureAccessAuthorisation(
        int $companyId,
        int $actorId,
        object $reservation,
        object $space,
        object $guest,
        int $partyRoleId,
    ): int {
        $existing = $this->db->table('pm_premise_access_authorisations')
            ->where('company_id', $companyId)
            ->where('premise_id', (int) $reservation->premise_id)
            ->where('space_id', (int) $space->id)
            ->where('party_role_id', $partyRoleId)
            ->where('status', 'active')
            ->where('starts_at', (string) $reservation->arrival_at)
            ->where('ends_at', (string) $reservation->departure_at)
            ->first();
        if ($existing !== null) {
            return (int) $existing->id;
        }

        return (int) $this->db->table('pm_premise_access_authorisations')->insertGetId([
            'company_id' => $companyId,
            'user_id' => $actorId,
            'premise_id' => (int) $reservation->premise_id,
            'space_id' => (int) $space->id,
            'party_role_id' => $partyRoleId,
            'subject_name' => (string) $guest->display_name,
            'purpose' => 'Accommodation stay',
            'access_scope' => 'space',
            'status' => 'active',
            'starts_at' => (string) $reservation->arrival_at,
            'ends_at' => (string) $reservation->departure_at,
            'metadata' => json_encode([
                'accommodation_managed' => true,
                'accommodation_reservation_id' => (int) $reservation->id,
                'accommodation_guest_id' => (int) $guest->id,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function partyRole(string $reservationType): string
    {
        return match ($reservationType) {
            'student' => 'student',
            'rooming_house' => 'resident',
            'ndis_sta', 'ndis_mta' => 'participant',
            default => 'guest',
        };
    }

    private function occupancyType(string $reservationType): string
    {
        return match ($reservationType) {
            'student', 'rooming_house' => 'resident',
            'ndis_sta', 'ndis_mta' => 'service_client',
            default => 'temporary_guest',
        };
    }

    /** @return array<string,mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (! is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

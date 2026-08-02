<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Application\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\NDIS\Application\Services\NDISActionSupport;
use App\Domains\WorkCore\System\Modules\NDIS\Domain\ParticipantStatus;
use App\Domains\WorkCore\System\References\TypedReference;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class UpsertNDISParticipant implements BusinessActionHandlerContract
{
    public function __construct(private ConnectionInterface $db, private NDISActionSupport $records) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $payload = $request->payload;
        $status = (string) ($payload['status'] ?? 'prospective');
        ParticipantStatus::assertAllowed($status);
        $funding = (string) ($payload['funding_management'] ?? 'unknown');
        if (! in_array($funding, ['agency_managed', 'plan_managed', 'self_managed', 'mixed', 'unknown'], true)) {
            throw new DomainException('Unsupported NDIS funding-management type.');
        }
        if (isset($payload['date_of_birth']) && new DateTimeImmutable((string) $payload['date_of_birth']) > new DateTimeImmutable('today')) {
            throw new DomainException('Participant date of birth cannot be in the future.');
        }

        $publicId = trim((string) ($payload['participant_public_id'] ?? '')) ?: (string) Str::ulid();
        $record = $this->db->transaction(function () use ($request, $payload, $status, $funding, $publicId): object {
            $existing = $this->db->table('tz_ndis_participants')
                ->where('company_id', $request->companyId)->where('public_id', $publicId)->whereNull('deleted_at')
                ->lockForUpdate()->first();
            $ndisNumber = preg_replace('/\s+/', '', strtoupper(trim((string) ($payload['ndis_number'] ?? ''))));
            $numberValues = [];
            if ($ndisNumber !== '') {
                if (! preg_match('/^[A-Z0-9-]{5,32}$/', $ndisNumber)) throw new DomainException('NDIS number format is invalid.');
                $numberValues = [
                    'ndis_number_encrypted' => Crypt::encryptString($ndisNumber),
                    'ndis_number_hash' => hash('sha256', $ndisNumber),
                    'ndis_number_last4' => substr($ndisNumber, -4),
                ];
            }
            $values = $numberValues + [
                'company_id' => $request->companyId,
                'customer_id' => $this->records->customerId($request->companyId, $payload['customer_public_id'] ?? null),
                'premise_party_role_id' => $this->records->partyRoleId($request->companyId, $payload['premise_party_role_public_id'] ?? null),
                'occupancy_id' => $this->records->occupancyId($request->companyId, $payload['occupancy_public_id'] ?? null),
                'portal_user_id' => isset($payload['portal_user_id']) ? (int) $payload['portal_user_id'] : ($existing->portal_user_id ?? null),
                'participant_reference' => isset($payload['participant_reference']) ? trim((string) $payload['participant_reference']) : ($existing->participant_reference ?? null),
                'first_name' => trim((string) ($payload['first_name'] ?? $existing->first_name ?? '')),
                'last_name' => trim((string) ($payload['last_name'] ?? $existing->last_name ?? '')),
                'preferred_name' => isset($payload['preferred_name']) ? trim((string) $payload['preferred_name']) : ($existing->preferred_name ?? null),
                'display_name' => trim((string) ($payload['display_name'] ?? trim((string) ($payload['preferred_name'] ?? $payload['first_name'] ?? $existing->display_name ?? '') . ' ' . (string) ($payload['last_name'] ?? '')))),
                'date_of_birth' => $payload['date_of_birth'] ?? ($existing->date_of_birth ?? null),
                'pronouns' => $payload['pronouns'] ?? ($existing->pronouns ?? null),
                'email' => $payload['email'] ?? ($existing->email ?? null),
                'phone' => $payload['phone'] ?? ($existing->phone ?? null),
                'preferred_communication' => (string) ($payload['preferred_communication'] ?? $existing->preferred_communication ?? 'phone'),
                'preferred_language' => $payload['preferred_language'] ?? ($existing->preferred_language ?? null),
                'interpreter_required' => (bool) ($payload['interpreter_required'] ?? $existing->interpreter_required ?? false),
                'status' => $status,
                'funding_management' => $funding,
                'consent_status' => (string) ($payload['consent_status'] ?? $existing->consent_status ?? 'pending'),
                'consent_review_on' => $payload['consent_review_on'] ?? ($existing->consent_review_on ?? null),
                'risk_level' => (string) ($payload['risk_level'] ?? $existing->risk_level ?? 'standard'),
                'restricted_access' => (bool) ($payload['restricted_access'] ?? $existing->restricted_access ?? false),
                'metadata' => json_encode((array) ($payload['metadata'] ?? $this->records->decode($existing->metadata ?? null)), JSON_THROW_ON_ERROR),
                'updated_by_user_id' => $request->actorId,
                'updated_at' => now(),
            ];
            if ($values['first_name'] === '' || $values['last_name'] === '' || $values['display_name'] === '') {
                throw new DomainException('Participant first name, last name and display name are required.');
            }
            if ($existing === null) {
                $values += ['public_id' => $publicId, 'created_by_user_id' => $request->actorId, 'created_at' => now()];
                $this->db->table('tz_ndis_participants')->insert($values);
            } else {
                $this->db->table('tz_ndis_participants')->where('id', (int) $existing->id)->update($values);
            }
            return $this->records->participant($request->companyId, $publicId);
        }, 3);

        $data = [
            'public_id' => $publicId, 'display_name' => (string) $record->display_name, 'status' => (string) $record->status,
            'funding_management' => (string) $record->funding_management,
            'ndis_number_masked' => $record->ndis_number_last4 ? '••••' . $record->ndis_number_last4 : null,
            'restricted_access' => (bool) $record->restricted_access,
        ];
        return new ActionHandlerResult($data, new TypedReference('ndis_participant', $publicId), [
            new PendingDomainEvent('workcore.ndis.participant.upserted', 1, ['participant' => $data]),
        ]);
    }
}

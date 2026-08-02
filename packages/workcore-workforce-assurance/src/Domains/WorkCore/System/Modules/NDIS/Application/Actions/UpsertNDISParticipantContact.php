<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Application\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\NDIS\Application\Services\NDISActionSupport;
use App\Domains\WorkCore\System\References\TypedReference;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class UpsertNDISParticipantContact implements BusinessActionHandlerContract
{
    public function __construct(private ConnectionInterface $db, private NDISActionSupport $records) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $payload = $request->payload; $participant = $this->records->participant($request->companyId, (string) $payload['participant_public_id']);
        $type = (string) ($payload['contact_type'] ?? 'emergency_contact');
        if (! in_array($type, ['nominee','guardian','plan_manager','support_coordinator','emergency_contact','advocate','family','other'], true)) throw new DomainException('Unsupported participant contact type.');
        $publicId = trim((string) ($payload['contact_public_id'] ?? '')) ?: (string) Str::ulid();
        $this->db->transaction(function () use ($request, $payload, $participant, $type, $publicId): void {
            $existing = $this->db->table('tz_ndis_participant_contacts')->where('company_id', $request->companyId)->where('public_id', $publicId)->whereNull('deleted_at')->lockForUpdate()->first();
            $values = [
                'company_id' => $request->companyId, 'participant_id' => (int) $participant->id,
                'customer_contact_id' => $this->records->customerContactId($request->companyId, $payload['customer_contact_public_id'] ?? null),
                'contact_type' => $type, 'display_name' => trim((string) ($payload['display_name'] ?? $existing->display_name ?? '')),
                'organisation_name' => $payload['organisation_name'] ?? ($existing->organisation_name ?? null),
                'relationship' => $payload['relationship'] ?? ($existing->relationship ?? null), 'email' => $payload['email'] ?? ($existing->email ?? null),
                'phone' => $payload['phone'] ?? ($existing->phone ?? null),
                'authority_scope' => json_encode((array) ($payload['authority_scope'] ?? $this->records->decode($existing->authority_scope ?? null)), JSON_THROW_ON_ERROR),
                'is_primary' => (bool) ($payload['is_primary'] ?? $existing->is_primary ?? false),
                'may_receive_information' => (bool) ($payload['may_receive_information'] ?? $existing->may_receive_information ?? false),
                'may_make_decisions' => (bool) ($payload['may_make_decisions'] ?? $existing->may_make_decisions ?? false),
                'valid_from' => $payload['valid_from'] ?? ($existing->valid_from ?? null), 'valid_until' => $payload['valid_until'] ?? ($existing->valid_until ?? null),
                'consent_status' => (string) ($payload['consent_status'] ?? $existing->consent_status ?? 'pending'),
                'metadata' => json_encode((array) ($payload['metadata'] ?? $this->records->decode($existing->metadata ?? null)), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ];
            if ($values['display_name'] === '') throw new DomainException('Participant contact display name is required.');
            if ($values['is_primary']) $this->db->table('tz_ndis_participant_contacts')->where('participant_id', (int) $participant->id)->where('contact_type', $type)->where('public_id', '<>', $publicId)->update(['is_primary' => false, 'updated_at' => now()]);
            if ($existing === null) $this->db->table('tz_ndis_participant_contacts')->insert($values + ['public_id' => $publicId, 'created_by_user_id' => $request->actorId, 'created_at' => now()]);
            else $this->db->table('tz_ndis_participant_contacts')->where('id', (int) $existing->id)->update($values);
        }, 3);
        $data = ['public_id' => $publicId, 'participant_public_id' => (string) $participant->public_id, 'contact_type' => $type];
        return new ActionHandlerResult($data, new TypedReference('ndis_participant_contact', $publicId), [new PendingDomainEvent('workcore.ndis.contact.upserted', 1, $data)]);
    }
}

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

final class LinkNDISParticipantIncident implements BusinessActionHandlerContract
{
    public function __construct(private ConnectionInterface $db, private NDISActionSupport $records) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $payload = $request->payload;
        $participant = $this->records->participant($request->companyId, (string) $payload['participant_public_id']);
        $incidentId = $this->records->incidentId($request->companyId, (string) $payload['incident_public_id']);
        if ($incidentId === null) throw new DomainException('A canonical safety incident is required.');
        $deliveryId = null;
        if (! empty($payload['delivery_public_id'])) {
            $delivery = $this->records->delivery($request->companyId, (string) $payload['delivery_public_id']);
            if ((int) $delivery->participant_id !== (int) $participant->id) throw new DomainException('Incident delivery belongs to another participant.');
            $deliveryId = (int) $delivery->id;
        }
        $existing = $this->db->table('tz_ndis_incident_links')->where('participant_id', (int) $participant->id)->where('incident_id', $incidentId)->first();
        $publicId = (string) ($existing->public_id ?? Str::ulid());
        $values = [
            'company_id' => $request->companyId, 'participant_id' => (int) $participant->id, 'incident_id' => $incidentId,
            'delivery_id' => $deliveryId, 'relationship' => (string) ($payload['relationship'] ?? 'affected_participant'),
            'participant_impact' => (string) ($payload['participant_impact'] ?? 'none'),
            'restrictive_practice' => (bool) ($payload['restrictive_practice'] ?? false),
            'reportable_to_ndis' => (bool) ($payload['reportable_to_ndis'] ?? false),
            'reported_to_ndis_at' => $payload['reported_to_ndis_at'] ?? null, 'follow_up' => $payload['follow_up'] ?? null,
            'metadata' => json_encode((array) ($payload['metadata'] ?? []), JSON_THROW_ON_ERROR), 'created_by_user_id' => $request->actorId,
            'updated_at' => now(),
        ];
        if ($existing === null) $this->db->table('tz_ndis_incident_links')->insert($values + ['public_id' => $publicId, 'created_at' => now()]);
        else $this->db->table('tz_ndis_incident_links')->where('id', (int) $existing->id)->update($values);
        $data = ['public_id' => $publicId, 'participant_public_id' => (string) $participant->public_id, 'incident_public_id' => (string) $payload['incident_public_id'], 'reportable_to_ndis' => $values['reportable_to_ndis']];
        return new ActionHandlerResult($data, new TypedReference('ndis_incident_link', $publicId), [new PendingDomainEvent('workcore.ndis.incident.linked', 1, $data)]);
    }
}

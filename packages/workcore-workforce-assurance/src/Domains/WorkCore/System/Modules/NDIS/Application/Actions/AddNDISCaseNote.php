<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Application\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Modules\NDIS\Application\Services\NDISActionSupport;
use App\Domains\WorkCore\System\Modules\NDIS\Domain\RecordVisibility;
use App\Domains\WorkCore\System\References\TypedReference;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class AddNDISCaseNote implements BusinessActionHandlerContract
{
    public function __construct(private ConnectionInterface $db, private NDISActionSupport $records, private PermissionResolverContract $permissions) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $payload = $request->payload; $participant = $this->records->participant($request->companyId, (string) $payload['participant_public_id']);
        $visibility = (string) ($payload['visibility'] ?? 'team'); $sensitivity = (string) ($payload['sensitivity'] ?? 'normal');
        RecordVisibility::assertAllowed($visibility); RecordVisibility::assertSensitivity($sensitivity);
        if ($visibility === 'restricted' && ! $this->permissions->allows($request->actorId, $request->companyId, (string) config('workcore.ndis.permissions.restricted_notes'))) {
            throw new AuthorizationException('Restricted NDIS case-note permission is required.');
        }
        if ((bool) $participant->restricted_access && $visibility !== 'restricted') throw new DomainException('Restricted participants require restricted case-note visibility.');
        $body = trim((string) ($payload['body'] ?? '')); if ($body === '') throw new DomainException('Case note body is required.');
        $deliveryId = null; if (! empty($payload['delivery_public_id'])) { $delivery = $this->records->delivery($request->companyId, (string) $payload['delivery_public_id']); if ((int) $delivery->participant_id !== (int) $participant->id) throw new DomainException('Case-note delivery belongs to another participant.'); $deliveryId = (int) $delivery->id; }
        $goalId = null; if (! empty($payload['goal_public_id'])) { $goal = $this->records->goal($request->companyId, (string) $payload['goal_public_id']); if ((int) $goal->participant_id !== (int) $participant->id) throw new DomainException('Case-note goal belongs to another participant.'); $goalId = (int) $goal->id; }
        $publicId = (string) Str::ulid();
        $this->db->table('tz_ndis_case_notes')->insert([
            'public_id' => $publicId, 'company_id' => $request->companyId, 'participant_id' => (int) $participant->id,
            'delivery_id' => $deliveryId, 'goal_id' => $goalId, 'worker_id' => $this->records->workerId($request->companyId, $payload['worker_public_id'] ?? null),
            'note_type' => (string) ($payload['note_type'] ?? 'progress'), 'visibility' => $visibility, 'sensitivity' => $sensitivity,
            'occurred_at' => $payload['occurred_at'] ?? now(), 'summary' => isset($payload['summary']) ? trim((string) $payload['summary']) : null,
            'body_encrypted' => Crypt::encryptString($body), 'body_hash' => hash('sha256', $body),
            'shared_with_participant_at' => ! empty($payload['shared_with_participant']) ? now() : null,
            'metadata' => json_encode((array) ($payload['metadata'] ?? []), JSON_THROW_ON_ERROR), 'author_user_id' => $request->actorId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $data = ['public_id' => $publicId, 'participant_public_id' => (string) $participant->public_id, 'note_type' => (string) ($payload['note_type'] ?? 'progress'), 'visibility' => $visibility, 'sensitivity' => $sensitivity];
        return new ActionHandlerResult($data, new TypedReference('ndis_case_note', $publicId), [new PendingDomainEvent('workcore.ndis.case_note.added', 1, $data)]);
    }
}

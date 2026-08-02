<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Application\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Modules\NDIS\Application\Services\NDISActionSupport;
use App\Domains\WorkCore\System\Modules\NDIS\Domain\RecordVisibility;
use App\Domains\WorkCore\System\References\TypedReference;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class UpsertNDISGoal implements BusinessActionHandlerContract
{
    public function __construct(private ConnectionInterface $db, private NDISActionSupport $records) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $payload = $request->payload; $participant = $this->records->participant($request->companyId, (string) $payload['participant_public_id']);
        $visibility = (string) ($payload['visibility'] ?? 'team'); RecordVisibility::assertAllowed($visibility);
        $planId = null;
        if (! empty($payload['plan_public_id'])) { $plan = $this->records->plan($request->companyId, (string) $payload['plan_public_id']); if ((int) $plan->participant_id !== (int) $participant->id) throw new DomainException('Goal plan belongs to another participant.'); $planId = (int) $plan->id; }
        $publicId = trim((string) ($payload['goal_public_id'] ?? '')) ?: (string) Str::ulid();
        $existing = $this->db->table('tz_ndis_participant_goals')->where('company_id', $request->companyId)->where('public_id', $publicId)->whereNull('deleted_at')->first();
        $values = [
            'company_id' => $request->companyId, 'participant_id' => (int) $participant->id, 'plan_id' => $planId,
            'status' => (string) ($payload['status'] ?? $existing->status ?? 'active'), 'title' => trim((string) ($payload['title'] ?? $existing->title ?? '')),
            'description' => $payload['description'] ?? ($existing->description ?? null), 'target_on' => $payload['target_on'] ?? ($existing->target_on ?? null),
            'achieved_on' => $payload['achieved_on'] ?? ($existing->achieved_on ?? null), 'outcome' => $payload['outcome'] ?? ($existing->outcome ?? null),
            'visibility' => $visibility, 'sort_order' => (int) ($payload['sort_order'] ?? $existing->sort_order ?? 0),
            'metadata' => json_encode((array) ($payload['metadata'] ?? $this->records->decode($existing->metadata ?? null)), JSON_THROW_ON_ERROR), 'updated_at' => now(),
        ];
        if ($values['title'] === '') throw new DomainException('NDIS participant goal title is required.');
        if ($existing === null) $this->db->table('tz_ndis_participant_goals')->insert($values + ['public_id' => $publicId, 'created_by_user_id' => $request->actorId, 'created_at' => now()]);
        else $this->db->table('tz_ndis_participant_goals')->where('id', (int) $existing->id)->update($values);
        $data = ['public_id' => $publicId, 'participant_public_id' => (string) $participant->public_id, 'status' => $values['status'], 'title' => $values['title']];
        return new ActionHandlerResult($data, new TypedReference('ndis_participant_goal', $publicId), [new PendingDomainEvent('workcore.ndis.goal.upserted', 1, $data)]);
    }
}

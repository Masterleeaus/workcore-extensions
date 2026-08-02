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

final class MatchNDISWorker implements BusinessActionHandlerContract
{
    public function __construct(private ConnectionInterface $db, private NDISActionSupport $records) {}
    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $payload = $request->payload; $participant = $this->records->participant($request->companyId, (string) $payload['participant_public_id']);
        $workerId = $this->records->workerId($request->companyId, (string) $payload['worker_public_id']);
        if ($workerId === null) throw new DomainException('A canonical worker is required.');
        $supportClass = trim((string) ($payload['support_class'] ?? '')) ?: null;
        $existing = $this->db->table('tz_ndis_worker_matches')->where('participant_id', (int) $participant->id)->where('worker_id', $workerId)->where('support_class', $supportClass)->whereNull('deleted_at')->first();
        $publicId = (string) ($existing->public_id ?? Str::ulid());
        $status = (string) ($payload['status'] ?? 'proposed');
        if (! in_array($status, ['proposed','approved','declined','inactive'], true)) throw new DomainException('Unsupported worker-match status.');
        $score = isset($payload['compatibility_score']) ? (int) $payload['compatibility_score'] : null;
        if ($score !== null && ($score < 0 || $score > 100)) throw new DomainException('Compatibility score must be between zero and one hundred.');
        $values = [
            'company_id' => $request->companyId, 'participant_id' => (int) $participant->id, 'worker_id' => $workerId,
            'support_class' => $supportClass, 'status' => $status, 'compatibility_score' => $score,
            'matched_preferences' => json_encode((array) ($payload['matched_preferences'] ?? []), JSON_THROW_ON_ERROR),
            'restrictions' => json_encode((array) ($payload['restrictions'] ?? []), JSON_THROW_ON_ERROR), 'reason' => $payload['reason'] ?? null,
            'starts_on' => $payload['starts_on'] ?? null, 'ends_on' => $payload['ends_on'] ?? null,
            'approved_by_user_id' => $status === 'approved' ? $request->actorId : null, 'approved_at' => $status === 'approved' ? now() : null,
            'metadata' => json_encode((array) ($payload['metadata'] ?? []), JSON_THROW_ON_ERROR), 'updated_at' => now(),
        ];
        if ($existing === null) $this->db->table('tz_ndis_worker_matches')->insert($values + ['public_id' => $publicId, 'created_at' => now()]);
        else $this->db->table('tz_ndis_worker_matches')->where('id', (int) $existing->id)->update($values);
        $data = ['public_id' => $publicId, 'participant_public_id' => (string) $participant->public_id, 'worker_public_id' => (string) $payload['worker_public_id'], 'status' => $status, 'compatibility_score' => $score];
        return new ActionHandlerResult($data, new TypedReference('ndis_worker_match', $publicId), [new PendingDomainEvent('workcore.ndis.worker.matched', 1, $data)]);
    }
}

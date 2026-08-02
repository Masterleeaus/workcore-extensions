<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Intelligence\Observations\IntelligenceObservation;
use App\Domains\WorkCore\System\Intelligence\Observations\ObservationRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
use Illuminate\Support\Str;

final class RecordIntelligenceObservation implements BusinessActionHandlerContract
{
    public function __construct(private ObservationRepositoryContract $observations) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $publicId = (string) Str::uuid();
        $observation = new IntelligenceObservation(
            publicId: $publicId,
            companyId: $request->companyId,
            domain: trim((string) ($request->payload['domain'] ?? '')),
            type: trim((string) ($request->payload['type'] ?? '')),
            summary: trim((string) ($request->payload['summary'] ?? '')),
            confidence: (float) ($request->payload['confidence'] ?? 0.0),
            evidence: array_values((array) ($request->payload['evidence'] ?? [])),
            dataGaps: array_values(array_map('strval', (array) ($request->payload['data_gaps'] ?? []))),
        );
        $this->observations->save($observation, $request->actorId);

        return new ActionHandlerResult(
            data: $observation->toArray(),
            aggregate: new TypedReference('ai_observation', $publicId),
            events: [new PendingDomainEvent('workcore.intelligence.observation_recorded', 1, [
                'observation_public_id' => $publicId,
                'domain' => $observation->domain,
                'type' => $observation->type,
                'confidence' => $observation->confidence,
            ])],
        );
    }
}

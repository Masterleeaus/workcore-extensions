<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Intelligence\Recommendations\IntelligenceRecommendation;
use App\Domains\WorkCore\System\Intelligence\Recommendations\RecommendationRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
use Illuminate\Support\Str;

final class ProposeIntelligenceRecommendation implements BusinessActionHandlerContract
{
    public function __construct(private RecommendationRepositoryContract $recommendations) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $publicId = (string) Str::uuid();
        $recommendation = new IntelligenceRecommendation(
            publicId: $publicId,
            companyId: $request->companyId,
            observationPublicId: isset($request->payload['observation_public_id']) ? trim((string) $request->payload['observation_public_id']) : null,
            summary: trim((string) ($request->payload['summary'] ?? '')),
            proposedAction: trim((string) ($request->payload['proposed_action'] ?? '')),
            confidence: (float) ($request->payload['confidence'] ?? 0.0),
            status: 'review_required',
            successMetrics: array_values(array_map('strval', (array) ($request->payload['success_metrics'] ?? []))),
            risk: trim((string) ($request->payload['risk'] ?? 'medium')),
        );
        $this->recommendations->save($recommendation, $request->actorId);

        return new ActionHandlerResult(
            data: $recommendation->toArray(),
            aggregate: new TypedReference('ai_recommendation', $publicId),
            events: [new PendingDomainEvent('workcore.intelligence.recommendation_proposed', 1, [
                'recommendation_public_id' => $publicId,
                'observation_public_id' => $recommendation->observationPublicId,
                'risk' => $recommendation->risk,
                'confidence' => $recommendation->confidence,
            ])],
        );
    }
}

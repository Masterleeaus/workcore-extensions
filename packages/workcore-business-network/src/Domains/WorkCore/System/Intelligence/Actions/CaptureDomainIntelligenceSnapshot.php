<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainActionAuthorizer;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainIntelligenceContextService;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainIntelligenceRegistry;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainIntelligenceRequest;
use App\Domains\WorkCore\System\Intelligence\Observations\IntelligenceObservation;
use App\Domains\WorkCore\System\Intelligence\Observations\ObservationRepositoryContract;
use App\Domains\WorkCore\System\Intelligence\Recommendations\IntelligenceRecommendation;
use App\Domains\WorkCore\System\Intelligence\Recommendations\RecommendationRepositoryContract;
use App\Domains\WorkCore\System\References\TypedReference;
use Illuminate\Support\Str;

final class CaptureDomainIntelligenceSnapshot implements BusinessActionHandlerContract
{
    public function __construct(
        private DomainIntelligenceContextService $contexts,
        private DomainIntelligenceRegistry $registry,
        private ObservationRepositoryContract $observations,
        private RecommendationRepositoryContract $recommendations,
        private PermissionResolverContract $permissions,
        private DomainActionAuthorizer $actionAuthorizer,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $domains = array_values(array_unique(array_map('strval', (array) ($request->payload['domains'] ?? []))));
        if ($domains === []) {
            $domains = array_keys($this->registry->all());
        }
        $maxDomains = (int) config('workcore.intelligence.domains.max_domains_per_snapshot', 7);
        if (count($domains) > $maxDomains) {
            throw new \InvalidArgumentException("At most {$maxDomains} domain adapters may be captured in one snapshot.");
        }

        foreach ($domains as $domain) {
            $adapter = $this->registry->require($domain);
            if (! $this->permissions->allows($request->actorId, $request->companyId, $adapter->viewPermission())) {
                abort(403);
            }
        }

        $domainRequest = new DomainIntelligenceRequest(
            companyId: $request->companyId,
            actorId: $request->actorId,
            maxItems: (int) ($request->payload['max_items'] ?? 50),
        );
        $contexts = $this->contexts->buildMany($domains, $domainRequest);
        $createRecommendations = (bool) ($request->payload['create_recommendations'] ?? true);
        $observationIds = [];
        $recommendationIds = [];

        foreach ($contexts as $context) {
            foreach ($context->signals as $signal) {
                $observationId = (string) Str::uuid();
                $observation = new IntelligenceObservation(
                    publicId: $observationId,
                    companyId: $request->companyId,
                    domain: $context->domain,
                    type: $signal->type,
                    summary: $signal->summary,
                    confidence: $signal->confidence,
                    evidence: $signal->evidence,
                    dataGaps: array_values(array_unique(array_merge($context->dataGaps, $signal->dataGaps))),
                );
                $this->observations->save($observation, $request->actorId, $request->idempotencyKey);
                $observationIds[] = $observationId;

                $allowedAction = $this->actionAuthorizer->allowedSuggested(
                    $context->actions,
                    $signal->suggestedAction,
                    $request->actorId,
                    $request->companyId,
                );
                if ($createRecommendations && $allowedAction !== null) {
                    $recommendationId = (string) Str::uuid();
                    $recommendation = new IntelligenceRecommendation(
                        publicId: $recommendationId,
                        companyId: $request->companyId,
                        observationPublicId: $observationId,
                        summary: $signal->summary,
                        proposedAction: $allowedAction->actionKey,
                        confidence: $signal->confidence,
                        status: 'review_required',
                        successMetrics: ["Resolve signal {$signal->type}", 'No unauthorised action is executed'],
                        risk: $allowedAction->risk,
                    );
                    $this->recommendations->save($recommendation, $request->actorId, $request->idempotencyKey);
                    $recommendationIds[] = $recommendationId;
                }
            }
        }

        return new ActionHandlerResult(
            data: [
                'domains' => array_keys($contexts),
                'contexts' => array_map(
                    fn ($context): array => $context->toArray($this->actionAuthorizer->allowed(
                        $context->actions,
                        $request->actorId,
                        $request->companyId,
                    )),
                    $contexts,
                ),
                'observation_public_ids' => $observationIds,
                'recommendation_public_ids' => $recommendationIds,
                'execution_mode' => 'proposal_only',
            ],
            aggregate: $observationIds !== [] ? new TypedReference('ai_observation', $observationIds[0]) : null,
            events: [new PendingDomainEvent('workcore.intelligence.domain_snapshot_captured', 1, [
                'domains' => array_keys($contexts),
                'observation_count' => count($observationIds),
                'recommendation_count' => count($recommendationIds),
                'execution_mode' => 'proposal_only',
            ])],
        );
    }

}
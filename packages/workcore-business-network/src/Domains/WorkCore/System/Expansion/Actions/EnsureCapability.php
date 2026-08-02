<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionPlannerContract;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionRepositoryContract;
use App\Domains\WorkCore\System\Expansion\Services\CapabilityGapAnalyzer;
use App\Domains\WorkCore\System\Expansion\Services\CapabilityInventoryBuilder;
use App\Domains\WorkCore\System\References\TypedReference;
use InvalidArgumentException;

final class EnsureCapability implements BusinessActionHandlerContract
{
    public function __construct(
        private CapabilityInventoryBuilder $inventory,
        private CapabilityGapAnalyzer $analyzer,
        private ExpansionPlannerContract $planner,
        private ExpansionRepositoryContract $repository,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $requirement = trim((string) ($request->payload['requirement'] ?? ''));
        if (strlen($requirement) < 8 || strlen($requirement) > 4000) {
            throw new InvalidArgumentException('Capability requirement must contain between 8 and 4000 characters.');
        }

        $inventory = $this->inventory->build($request->companyId);
        $assessment = $this->analyzer->assess($requirement, $inventory);

        if ($assessment->status === 'existing') {
            return new ActionHandlerResult(
                data: [
                    'resolution' => 'existing_capability',
                    'assessment' => $assessment->toArray(),
                    'expansion_created' => false,
                ],
                events: [new PendingDomainEvent('workcore.capability.requirement_resolved', 1, [
                    'requirement' => $requirement,
                    'matched_key' => $assessment->matchedKey,
                    'match_type' => $assessment->matchType,
                    'score' => $assessment->score,
                ])],
            );
        }

        $callingAiProposal = isset($request->payload['proposed_plan']) && is_array($request->payload['proposed_plan'])
            ? $request->payload['proposed_plan']
            : null;
        $plan = $this->planner->plan(
            $requirement,
            $inventory,
            $request->companyId,
            $request->actorId,
            $assessment,
            $callingAiProposal,
        );
        $initialStatus = $plan['delivery_mode'] === 'native_module' ? 'native_build_required' : 'proposed';
        $expansion = $this->repository->createRequest(
            companyId: $request->companyId,
            actorId: $request->actorId,
            requirement: $requirement,
            normalisedRequirement: $assessment->normalisedRequirement,
            assessment: $assessment->toArray(),
            plan: $plan,
            status: $initialStatus,
        );

        $domain = null;
        $autonomy = (string) config('workcore.expansion.autonomy', 'assist');
        if (
            $autonomy === 'managed'
            && ($plan['delivery_mode'] ?? null) === 'adaptive_domain'
            && ($plan['risk'] ?? null) === 'low'
        ) {
            $domain = $this->repository->activateAdaptiveDomain(
                $request->companyId,
                (string) $expansion['public_id'],
                $request->actorId,
                (array) $plan['blueprint'],
            );
            $expansion = $this->repository->findRequest($request->companyId, (string) $expansion['public_id']) ?? $expansion;
        }

        return new ActionHandlerResult(
            data: [
                'resolution' => $domain ? 'adaptive_domain_activated' : ($assessment->status === 'extend_existing' ? 'extension_proposed' : 'expansion_proposed'),
                'assessment' => $assessment->toArray(),
                'expansion' => $expansion,
                'adaptive_domain' => $domain,
                'autonomy' => $autonomy,
            ],
            aggregate: new TypedReference('capability_expansion_request', (string) $expansion['public_id']),
            events: [new PendingDomainEvent('workcore.capability.expansion_requested', 1, [
                'request_public_id' => $expansion['public_id'],
                'delivery_mode' => $plan['delivery_mode'],
                'risk' => $plan['risk'],
                'auto_activated' => $domain !== null,
            ])],
        );
    }
}

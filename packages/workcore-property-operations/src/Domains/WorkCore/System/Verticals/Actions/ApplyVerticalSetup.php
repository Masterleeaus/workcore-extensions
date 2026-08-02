<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Actions;

use App\Domains\WorkCore\System\Actions\{ActionHandlerResult,ActionRequest,PendingDomainEvent};
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Verticals\Setup\{VerticalSetupApplicationService,VerticalSetupPlanCompiler};

final class ApplyVerticalSetup implements BusinessActionHandlerContract
{
    public function __construct(
        private VerticalSetupPlanCompiler $compiler,
        private VerticalSetupApplicationService $application,
    ) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $plan = $this->compiler->compile(
            (string) $request->payload['primary_vertical'],
            array_values(array_map('strval', (array) ($request->payload['add_ons'] ?? []))),
            (array) ($request->payload['answers'] ?? []),
        );
        $result = $this->application->apply($request->companyId, $request->actorId, $plan);

        return new ActionHandlerResult($result, events: [
            new PendingDomainEvent('workcore.vertical_setup.applied', 1, [
                'company_id' => $request->companyId,
                'primary_vertical' => $plan->primaryVertical,
                'add_ons' => $plan->addOns,
                'plan_hash' => $result['plan_hash'] ?? null,
            ]),
        ]);
    }
}

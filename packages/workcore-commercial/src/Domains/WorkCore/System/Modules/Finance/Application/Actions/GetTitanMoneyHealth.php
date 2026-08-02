<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Actions;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionResult;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ApprovalRequirement;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\GenerativeUiEnvelope;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\RiskAssessment;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\ResultStatus;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\RiskLevel;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\SurfaceType;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TitanMoneyAction;
use App\Domains\WorkCore\System\Modules\Finance\Support\TitanMoneyHealth;

final readonly class GetTitanMoneyHealth implements TitanMoneyAction
{
    public function __construct(private TitanMoneyHealth $health)
    {
    }

    public function authorize(ActionContext $context): void
    {
        // Authentication and active-company membership are enforced by the caller boundary.
    }

    public function assessRisk(ActionInput $input, ActionContext $context): RiskAssessment
    {
        return new RiskAssessment(RiskLevel::Low, ['read-only system diagnostics'], ApprovalRequirement::none());
    }

    public function execute(ActionInput $input, ActionContext $context): ActionResult
    {
        $report = $this->health->report();
        $ui = new GenerativeUiEnvelope(
            schemaVersion: '1.0',
            component: 'money.health',
            data: $report,
            surfaces: [SurfaceType::Workspace, SurfaceType::MobileCard, SurfaceType::TabletPanel],
            actions: [],
        );

        return new ActionResult(
            ResultStatus::Success,
            'money.health.ready',
            $report,
            $ui,
            [],
            null,
        );
    }
}

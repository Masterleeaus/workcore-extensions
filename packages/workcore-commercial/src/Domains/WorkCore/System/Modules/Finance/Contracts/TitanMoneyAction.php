<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionResult;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\RiskAssessment;

interface TitanMoneyAction
{
    public function authorize(ActionContext $context): void;

    public function assessRisk(ActionInput $input, ActionContext $context): RiskAssessment;

    public function execute(ActionInput $input, ActionContext $context): ActionResult;
}

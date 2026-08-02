<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ApprovalRequirement;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\RiskAssessment;

interface ApprovalGateway
{
    public function request(
        string $actionKey,
        RiskAssessment $risk,
        ActionContext $context,
    ): ApprovalRequirement;
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Contracts;

use App\Domains\WorkCore\System\Expansion\Data\CapabilityInventory;
use App\Domains\WorkCore\System\Expansion\Data\ExpansionAssessment;

interface ExpansionPlannerContract
{
    /** @param array<string,mixed>|null $callingAiProposal @return array<string,mixed> */
    public function plan(
        string $requirement,
        CapabilityInventory $inventory,
        int $companyId,
        int $actorId,
        ?ExpansionAssessment $assessment = null,
        ?array $callingAiProposal = null,
    ): array;
}

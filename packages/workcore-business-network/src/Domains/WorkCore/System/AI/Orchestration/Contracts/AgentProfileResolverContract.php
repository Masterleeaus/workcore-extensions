<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\Contracts;

use App\Domains\WorkCore\System\AI\Orchestration\DTO\ResolvedAgentProfile;

interface AgentProfileResolverContract
{
    public function resolve(int $companyId, ?string $agentPublicId): ResolvedAgentProfile;
}

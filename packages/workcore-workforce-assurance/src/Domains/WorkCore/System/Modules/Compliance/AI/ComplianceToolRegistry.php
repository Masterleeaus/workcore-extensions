<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Compliance\AI;
use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract,ToolDefinitionFactory as T};
final class ComplianceToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        return [T::read('compliance_search_register','Search company compliance requirements, obligations, certificates, hazards and incidents.','workcore.compliance.search',T::object(['filters'=>T::freeObject(),'perPage'=>T::integer(1,100)]),'restricted')];
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\TitanVault\AI;

use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract,ToolDefinitionFactory as T};

final class TitanVaultToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        return [
            T::read('vault_search_documents', 'Search authorised TitanVault document metadata without exposing stored credentials.', 'workcore.vault.document.search', T::object([
                'filters'=>T::freeObject(),'perPage'=>T::integer(1,100),
            ], [], false), 'restricted'),
        ];
    }
}

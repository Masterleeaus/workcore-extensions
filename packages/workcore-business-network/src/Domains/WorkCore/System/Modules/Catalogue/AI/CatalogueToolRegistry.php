<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Catalogue\AI;
use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract,ToolDefinitionFactory as T};
final class CatalogueToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        $id=T::string(26);
        return [
            T::read('catalogue_search_services','Search company services and service categories.','workcore.service.search',T::object(['query'=>T::string(255),'categoryPublicId'=>$id,'businessLinePublicId'=>$id,'perPage'=>T::integer(1,100)])),
            T::read('catalogue_list_service_variants','List variants and pricing options for a service.','workcore.service_variant.list',T::object(['servicePublicId'=>$id],['servicePublicId'])),
            T::read('catalogue_list_service_faqs','List frequently asked questions for a service.','workcore.service_faq.list',T::object(['servicePublicId'=>$id],['servicePublicId'])),
        ];
    }
}

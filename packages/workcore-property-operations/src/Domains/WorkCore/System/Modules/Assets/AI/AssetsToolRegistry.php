<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Assets\AI;
use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract,ToolDefinitionFactory as T};
final class AssetsToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        $id=T::string(26);
        return [
            T::read('assets_search','Search company assets and equipment.','workcore.asset.search',T::object(['filters'=>T::freeObject(),'perPage'=>T::integer(1,100)])),
            T::read('assets_get_availability','Check an asset availability and booking conflicts.','workcore.asset.availability',T::object(['assetPublicId'=>$id],['assetPublicId'])),
            T::read('assets_lookup_identifier','Find an asset by QR code, barcode, serial number or other identifier.','workcore.asset.identifier.lookup',T::object(['value'=>T::string(255),'type'=>T::string(80)],['value'])),
            T::read('assets_search_overdue_custody','Find overdue asset custody and return records.','workcore.asset.custody.overdue',T::object(['filters'=>T::freeObject()])),
        ];
    }
}

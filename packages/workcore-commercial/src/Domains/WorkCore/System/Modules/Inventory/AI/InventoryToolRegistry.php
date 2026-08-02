<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Inventory\AI;
use App\Domains\WorkCore\System\AI\Tools\{AiToolDefinition,DomainToolRegistryContract,ToolDefinitionFactory as T};
final class InventoryToolRegistry implements DomainToolRegistryContract
{
    /** @return list<AiToolDefinition> */
    public function registerTools(): array
    {
        $id=T::string(26);
        return [
            T::read('inventory_search_items','Search company inventory items and current stock information.','workcore.inventory.search',T::object(['filters'=>T::freeObject(),'perPage'=>T::integer(1,100)])),
            T::read('inventory_search_locations','List company stock locations.','workcore.stock_locations.search',T::object(['filters'=>T::freeObject()])),
            T::action('inventory_reserve_item','Reserve inventory for a work order or operational requirement.','workcore.stock.reserve',T::object([
                'item_public_id'=>$id,'location_public_id'=>$id,'quantity'=>T::number(0.0001),'reference_type'=>T::string(80),
                'reference_public_id'=>$id,'expires_at'=>T::string(40),'notes'=>T::string(5000),
            ],['item_public_id','quantity'],true)),
            T::action('inventory_release_reservation','Release an unused inventory reservation.','workcore.stock.release_reservation',T::object([
                'reservation_public_id'=>$id,'reason'=>T::string(5000),
            ],['reservation_public_id'])),
        ];
    }
}

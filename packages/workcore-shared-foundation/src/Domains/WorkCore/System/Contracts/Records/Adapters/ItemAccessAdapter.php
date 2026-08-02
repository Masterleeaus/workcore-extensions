<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\ItemAccessContract;

final class ItemAccessAdapter extends AbstractDatabaseRecordAccess implements ItemAccessContract
{
    protected function table(): string { return 'tz_inventory_items'; }
    protected function type(): string { return 'item'; }
    protected function columns(): array
    {
        return ['public_id','supply_item_public_id','sku','name','item_type','unit_of_measure','track_stock','lot_tracking','serial_tracking','active','default_unit_cost','default_reorder_point','default_reorder_quantity','description','metadata','created_at','updated_at'];
    }
}

<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Inventory\Providers;
use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\ItemAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\ItemAccessAdapter;
use App\Domains\WorkCore\System\Modules\Inventory\Actions\{ConsumeStockReservation,CreateInventoryItem,CreateStockLocation,ListStockLocations,RecordStockMovement,ReleaseStockReservation,ReserveInventoryItem,SearchInventory,SetInventoryReorderRule};
use App\Domains\WorkCore\System\Modules\Inventory\Contracts\InventoryRepositoryContract;
use App\Domains\WorkCore\System\Modules\Inventory\Repositories\EloquentInventoryRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;
final class WorkInventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InventoryRepositoryContract::class, EloquentInventoryRepository::class);
        $this->app->bind(ItemAccessContract::class, ItemAccessAdapter::class);
        $actions = $this->app->make(BusinessActionRegistry::class);
        $actions->register(new ActionDefinition('workcore.inventory_item.create', CreateInventoryItem::class, 'medium', true, 'workcore.inventory', (string) config('workcore.inventory.permissions.manage')));
        $actions->register(new ActionDefinition('workcore.stock_location.create', CreateStockLocation::class, 'medium', true, 'workcore.inventory', (string) config('workcore.inventory.permissions.locations')));
        $actions->register(new ActionDefinition('workcore.stock.record_movement', RecordStockMovement::class, 'high', true, 'workcore.inventory', (string) config('workcore.inventory.permissions.movements')));
        $actions->register(new ActionDefinition('workcore.stock.reserve', ReserveInventoryItem::class, 'medium', true, 'workcore.inventory', (string) config('workcore.inventory.permissions.reserve')));
        $actions->register(new ActionDefinition('workcore.stock.consume_reservation', ConsumeStockReservation::class, 'high', true, 'workcore.inventory', (string) config('workcore.inventory.permissions.consume')));
        $actions->register(new ActionDefinition('workcore.stock.release_reservation', ReleaseStockReservation::class, 'medium', true, 'workcore.inventory', (string) config('workcore.inventory.permissions.reserve')));
        $actions->register(new ActionDefinition('workcore.inventory.set_reorder_rule', SetInventoryReorderRule::class, 'medium', true, 'workcore.inventory', (string) config('workcore.inventory.permissions.manage')));
        $read = $this->app->make(ReadModelRegistry::class);
        $read->register(new ReadModelDefinition('workcore.inventory.search', SearchInventory::class, 'workcore.inventory', permission: (string) config('workcore.inventory.permissions.view')));
        $read->register(new ReadModelDefinition('workcore.stock_locations.search', ListStockLocations::class, 'workcore.inventory', permission: (string) config('workcore.inventory.permissions.view')));
    }
    public function boot(): void
    {
        if ((bool) config('workcore.inventory.routes_enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }
}

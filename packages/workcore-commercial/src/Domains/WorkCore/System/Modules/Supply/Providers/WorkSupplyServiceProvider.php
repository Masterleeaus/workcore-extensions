<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Supply\Providers;

use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Modules\Supply\Actions\{
    ApprovePurchaseOrder,
    CompleteStockCount,
    CreateInventoryCategory,
    CreatePurchaseOrder,
    CreateStockAdjustment,
    CreateStockCount,
    CreateStockTransfer,
    CreateSupplier,
    DispatchStockTransfer,
    PostStockAdjustment,
    RateSupplier,
    ReceiveGoods,
    ReceiveStockTransfer,
    SearchPurchaseOrders,
    SearchSuppliers
};
use App\Domains\WorkCore\System\Modules\Supply\Contracts\SupplyRepositoryContract;
use App\Domains\WorkCore\System\Modules\Supply\Repositories\EloquentSupplyRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;

final class WorkSupplyServiceProvider extends ServiceProvider
{
    public function register():void
    {
        $this->app->bind(SupplyRepositoryContract::class,EloquentSupplyRepository::class);
        $actions=$this->app->make(BusinessActionRegistry::class);
        $definitions=[
            ['workcore.inventory.category.create',CreateInventoryCategory::class,'low','manage_catalogue'],
            ['workcore.supply.supplier.create',CreateSupplier::class,'medium','manage_suppliers'],
            ['workcore.supply.supplier.rate',RateSupplier::class,'low','manage_suppliers'],
            ['workcore.supply.purchase_order.create',CreatePurchaseOrder::class,'medium','purchase'],
            ['workcore.supply.purchase_order.approve',ApprovePurchaseOrder::class,'high','approve'],
            ['workcore.supply.goods_receipt.post',ReceiveGoods::class,'high','receive'],
            ['workcore.supply.transfer.create',CreateStockTransfer::class,'medium','transfer'],
            ['workcore.supply.transfer.dispatch',DispatchStockTransfer::class,'high','transfer'],
            ['workcore.supply.transfer.receive',ReceiveStockTransfer::class,'high','transfer'],
            ['workcore.supply.stock_count.create',CreateStockCount::class,'medium','count'],
            ['workcore.supply.stock_count.complete',CompleteStockCount::class,'high','count'],
            ['workcore.supply.adjustment.create',CreateStockAdjustment::class,'high','adjust'],
            ['workcore.supply.adjustment.post',PostStockAdjustment::class,'critical','approve_adjustment'],
        ];
        foreach($definitions as[$key,$handler,$risk,$permission]){$actions->register(new ActionDefinition($key,$handler,$risk,true,'workcore.supply',(string)config("workcore.supply.permissions.{$permission}"),['domain'=>'supply_chain','canonical_owner'=>'WorkCore Supply']));}
        $reads=$this->app->make(ReadModelRegistry::class);$view=(string)config('workcore.supply.permissions.view');
        $reads->register(new ReadModelDefinition('workcore.supply.suppliers.search',SearchSuppliers::class,'workcore.supply',permission:$view));
        $reads->register(new ReadModelDefinition('workcore.supply.purchase_orders.search',SearchPurchaseOrders::class,'workcore.supply',permission:$view));
    }
    public function boot():void{if((bool)config('workcore.supply.routes_enabled',false)){$this->loadRoutesFrom(__DIR__.'/../routes/api.php');}}
}

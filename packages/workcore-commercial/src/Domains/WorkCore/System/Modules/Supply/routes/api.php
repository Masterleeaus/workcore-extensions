<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\Supply\Http\Controllers\SupplyController;
use Illuminate\Support\Facades\Route;
Route::prefix((string)config('workcore.supply.route_prefix'))->middleware((array)config('workcore.supply.middleware'))->name('api.workcore.v1.')->group(function():void{
    Route::get('suppliers',[SupplyController::class,'suppliers'])->name('suppliers.index');
    Route::post('suppliers',[SupplyController::class,'createSupplier'])->name('suppliers.store');
    Route::post('suppliers/{supplier}/ratings',[SupplyController::class,'rateSupplier'])->name('suppliers.ratings.store');
    Route::post('inventory-categories',[SupplyController::class,'createCategory'])->name('inventory-categories.store');
    Route::get('purchase-orders',[SupplyController::class,'purchaseOrders'])->name('purchase-orders.index');
    Route::post('purchase-orders',[SupplyController::class,'createPurchaseOrder'])->name('purchase-orders.store');
    Route::post('purchase-orders/{purchaseOrder}/approve',[SupplyController::class,'approvePurchaseOrder'])->name('purchase-orders.approve');
    Route::post('goods-receipts',[SupplyController::class,'receiveGoods'])->name('goods-receipts.store');
    Route::post('stock-transfers',[SupplyController::class,'createTransfer'])->name('stock-transfers.store');
    Route::post('stock-transfers/{transfer}/dispatch',[SupplyController::class,'dispatchTransfer'])->name('stock-transfers.dispatch');
    Route::post('stock-transfers/{transfer}/receive',[SupplyController::class,'receiveTransfer'])->name('stock-transfers.receive');
    Route::post('stock-counts',[SupplyController::class,'createStockCount'])->name('stock-counts.store');
    Route::post('stock-counts/{stockCount}/complete',[SupplyController::class,'completeStockCount'])->name('stock-counts.complete');
    Route::post('stock-adjustments',[SupplyController::class,'createAdjustment'])->name('stock-adjustments.store');
    Route::post('stock-adjustments/{adjustment}/post',[SupplyController::class,'postAdjustment'])->name('stock-adjustments.post');
});

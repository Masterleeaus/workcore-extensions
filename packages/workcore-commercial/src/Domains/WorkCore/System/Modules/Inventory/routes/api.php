<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\Inventory\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;
Route::prefix((string) config('workcore.inventory.route_prefix'))->middleware((array) config('workcore.inventory.middleware'))->name('api.workcore.v1.')->group(function (): void {
    Route::get('inventory-items', [InventoryController::class, 'index'])->name('inventory-items.index');
    Route::post('inventory-items', [InventoryController::class, 'store'])->name('inventory-items.store');
    Route::get('stock-locations', [InventoryController::class, 'locations'])->name('stock-locations.index');
    Route::post('stock-locations', [InventoryController::class, 'storeLocation'])->name('stock-locations.store');
    Route::post('inventory-items/{item}/movements', [InventoryController::class, 'movement'])->name('inventory-items.movements.store');
    Route::post('inventory-items/{item}/reservations', [InventoryController::class, 'reserve'])->name('inventory-items.reservations.store');
    Route::post('inventory-items/{item}/reorder-rule', [InventoryController::class, 'reorderRule'])->name('inventory-items.reorder-rule.store');
    Route::post('stock-reservations/{reservation}/consume', [InventoryController::class, 'consume'])->name('stock-reservations.consume');
    Route::post('stock-reservations/{reservation}/release', [InventoryController::class, 'release'])->name('stock-reservations.release');
});

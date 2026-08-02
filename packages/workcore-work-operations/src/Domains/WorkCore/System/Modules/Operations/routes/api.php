<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Modules\Operations\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('workcore.operations.route_prefix'))
    ->middleware((array) config('workcore.operations.middleware'))
    ->name('api.workcore.v1.')
    ->group(function (): void {
        Route::get('work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
        Route::post('work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
        Route::post('work-orders/{workOrder}/status', [WorkOrderController::class, 'changeStatus'])->name('work-orders.status');
        Route::post('work-orders/{workOrder}/time-entries', [WorkOrderController::class, 'addTimeEntry'])->name('work-orders.time-entries.store');
        Route::post('work-order-tasks/{task}/status', [WorkOrderController::class, 'updateTaskStatus'])->name('work-order-tasks.status');
    });

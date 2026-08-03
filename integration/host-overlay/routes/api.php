<?php

use App\Http\Controllers\Api\V1\Sync\OperationController;
use App\Http\Controllers\Api\V1\Titan\ToolController;
use App\Http\Controllers\Api\V1\WorkCore\ActionController;
use App\Http\Controllers\Api\V1\WorkCore\BusinessFlowController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'company.active', 'workcore.tenant', 'workcore.api'])
    ->prefix('v1')
    ->group(function (): void {
        Route::get('/user', fn (Request $request) => $request->user());

        Route::middleware('throttle:workcore-actions')->prefix('workcore')->group(function (): void {
            Route::get('/actions', [ActionController::class, 'index']);
            Route::get('/actions/{action}', [ActionController::class, 'show'])->where('action', '.*');
            Route::post('/actions/{action}/execute', [ActionController::class, 'execute'])->where('action', '.*');
            Route::post('/flows/customer-property-work-order', [BusinessFlowController::class, 'customerPropertyWorkOrder']);
        });

        Route::prefix('tools')->group(function (): void {
            Route::get('/', [ToolController::class, 'index']);
        });

        Route::middleware('throttle:offline-sync')->prefix('sync')->group(function (): void {
            Route::post('/operations', [OperationController::class, 'store']);
            Route::get('/operations/{operationId}', [OperationController::class, 'show']);
        });
    });

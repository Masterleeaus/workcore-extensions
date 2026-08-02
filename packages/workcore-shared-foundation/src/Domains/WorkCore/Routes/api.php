<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\WorkCore\ActionController;
use App\Http\Controllers\Api\V1\WorkCore\BusinessFlowController;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('workcore.api.route_prefix', 'api/v1/workcore'))
    ->middleware((array) config('workcore.api.middleware', ['api', 'auth:sanctum', 'workcore.tenant', 'workcore.api']))
    ->name('api.workcore.')
    ->group(function (): void {
        Route::middleware('throttle:workcore-actions')->group(function (): void {
            Route::get('actions', [ActionController::class, 'index'])->name('actions.index');
            Route::get('actions/{action}', [ActionController::class, 'show'])
                ->where('action', '.*')
                ->name('actions.show');
            Route::post('actions/{action}/execute', [ActionController::class, 'execute'])
                ->where('action', '.*')
                ->name('actions.execute');
            Route::post('flows/customer-property-work-order', [BusinessFlowController::class, 'customerPropertyWorkOrder'])
                ->name('flows.customer-property-work-order');
        });
    });

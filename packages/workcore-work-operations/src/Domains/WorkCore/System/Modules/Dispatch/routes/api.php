<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Modules\Dispatch\Http\Controllers\DispatchController;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('workcore.dispatch.route_prefix'))
    ->middleware((array) config('workcore.dispatch.middleware'))
    ->name('api.workcore.v1.')
    ->group(function (): void {
        Route::get('dispatch-board', [DispatchController::class, 'index'])->name('dispatch.index');
        Route::post('dispatch-assignments', [DispatchController::class, 'store'])->name('dispatch.store');
        Route::post('dispatch-assignments/{dispatch}/reassign', [DispatchController::class, 'reassign'])->name('dispatch.reassign');
        Route::post('dispatch-assignments/{dispatch}/status', [DispatchController::class, 'changeStatus'])->name('dispatch.status');
    });

<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Modules\Rosters\Http\Controllers\RosterUiController;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('workcore.rosters.ui_route_prefix', 'workcore/rosters'))
    ->middleware((array) config('workcore.rosters.ui_middleware', ['web', 'auth', 'workcore.tenant']))
    ->name('workcore.rosters.')
    ->group(function (): void {
        Route::get('/', [RosterUiController::class, 'index'])->name('index');
    });

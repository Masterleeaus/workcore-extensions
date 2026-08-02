<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Modules\Rosters\Http\Controllers\RosterController;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('workcore.rosters.route_prefix'))
    ->middleware((array) config('workcore.rosters.middleware'))
    ->name('api.workcore.v1.')
    ->group(function (): void {
        Route::get('availability', [RosterController::class, 'availability'])->name('availability.index');
        Route::post('availability', [RosterController::class, 'storeAvailability'])->name('availability.store');
        Route::get('rosters', [RosterController::class, 'index'])->name('rosters.index');
        Route::get('rosters/{roster}', [RosterController::class, 'show'])->name('rosters.show');
        Route::post('rosters', [RosterController::class, 'store'])->name('rosters.store');
        Route::post('roster-shifts', [RosterController::class, 'storeShift'])->name('roster-shifts.store');
        Route::post('roster-shifts/{shift}/assign', [RosterController::class, 'assignShift'])->name('roster-shifts.assign');
        Route::post('rosters/{roster}/publish', [RosterController::class, 'publish'])->name('rosters.publish');
    });

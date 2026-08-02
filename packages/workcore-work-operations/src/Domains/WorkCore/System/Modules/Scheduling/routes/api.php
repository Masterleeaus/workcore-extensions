<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Modules\Scheduling\Http\Controllers\AppointmentController;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('workcore.scheduling.route_prefix'))
    ->middleware((array) config('workcore.scheduling.middleware'))
    ->name('api.workcore.v1.')
    ->group(function (): void {
        Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::post('appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::post('appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
        Route::post('appointments/{appointment}/status', [AppointmentController::class, 'changeStatus'])->name('appointments.status');
    });

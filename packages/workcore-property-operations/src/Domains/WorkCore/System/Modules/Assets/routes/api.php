<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Modules\Assets\Http\Controllers\AssetController;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('workcore.assets.route_prefix'))
    ->middleware((array) config('workcore.assets.middleware'))
    ->name('api.workcore.v1.')
    ->group(function (): void {
        Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('assets/identifier-lookup', [AssetController::class, 'lookupIdentifier'])->name('assets.identifiers.lookup');
        Route::get('assets/custody/overdue', [AssetController::class, 'overdueCustody'])->name('assets.custody.overdue');
        Route::get('assets/{asset}/availability', [AssetController::class, 'availability'])->name('assets.availability.show');

        Route::post('asset-categories', [AssetController::class, 'storeCategory'])->name('asset-categories.store');
        Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
        Route::post('assets/supply-handoffs', [AssetController::class, 'supplyHandoff'])->name('assets.supply-handoffs.store');
        Route::post('assets/{asset}/assignments', [AssetController::class, 'assign'])->name('assets.assignments.store');
        Route::post('assets/{asset}/checkout', [AssetController::class, 'checkout'])->name('assets.checkout.store');
        Route::post('assets/{asset}/checkin', [AssetController::class, 'checkin'])->name('assets.checkin.store');
        Route::post('assets/{asset}/transfer', [AssetController::class, 'transfer'])->name('assets.transfer.store');
        Route::post('assets/{asset}/status', [AssetController::class, 'status'])->name('assets.status.store');
        Route::post('assets/{asset}/write-off', [AssetController::class, 'writeoff'])->name('assets.writeoff.store');
        Route::post('assets/{asset}/status-override', [AssetController::class, 'overrideStatus'])->name('assets.status-override.store');
        Route::post('assets/{asset}/conditions', [AssetController::class, 'condition'])->name('assets.conditions.store');
        Route::post('assets/{asset}/damage-reports', [AssetController::class, 'damage'])->name('assets.damage-reports.store');
        Route::post('assets/{asset}/maintenance-plans', [AssetController::class, 'maintenancePlan'])->name('assets.maintenance-plans.store');
        Route::post('assets/{asset}/maintenance', [AssetController::class, 'maintenance'])->name('assets.maintenance.store');
        Route::post('assets/{asset}/maintenance/{maintenance}/start', [AssetController::class, 'startMaintenance'])->name('assets.maintenance.start');
        Route::post('assets/{asset}/maintenance/{maintenance}/complete', [AssetController::class, 'completeMaintenance'])->name('assets.maintenance.complete');
        Route::post('assets/{asset}/meter-readings', [AssetController::class, 'meterReading'])->name('assets.meter-readings.store');
        Route::post('assets/{asset}/calibrations', [AssetController::class, 'calibration'])->name('assets.calibrations.store');
        Route::post('assets/{asset}/warranties', [AssetController::class, 'warranty'])->name('assets.warranties.store');
        Route::post('assets/{asset}/warranties/{warranty}/claims', [AssetController::class, 'warrantyClaim'])->name('assets.warranty-claims.store');
        Route::post('assets/{asset}/warranty-claims/{claim}/transition', [AssetController::class, 'updateWarrantyClaim'])->name('assets.warranty-claims.transition');
        Route::post('assets/{asset}/identifiers', [AssetController::class, 'identifier'])->name('assets.identifiers.store');
        Route::post('assets/{asset}/documents', [AssetController::class, 'document'])->name('assets.documents.store');
    });

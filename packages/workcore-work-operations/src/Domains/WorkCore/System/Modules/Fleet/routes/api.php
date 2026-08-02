<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\Fleet\Http\Controllers\FleetController;
use Illuminate\Support\Facades\Route;
Route::prefix((string)config('workcore.fleet.route_prefix'))->middleware((array)config('workcore.fleet.middleware'))->name('api.workcore.v1.')->group(function():void{
    Route::get('fleet/vehicles',[FleetController::class,'index'])->name('fleet.vehicles.index');
    Route::post('fleet/vehicles',[FleetController::class,'store'])->name('fleet.vehicles.store');
    Route::post('fleet/vehicles/{vehicle}/mileage',[FleetController::class,'mileage'])->name('fleet.vehicles.mileage');
    Route::post('fleet/vehicles/{vehicle}/assignments',[FleetController::class,'assign'])->name('fleet.vehicles.assign');
    Route::post('fleet/assignments/{assignment}/return',[FleetController::class,'returnVehicle'])->name('fleet.assignments.return');
});

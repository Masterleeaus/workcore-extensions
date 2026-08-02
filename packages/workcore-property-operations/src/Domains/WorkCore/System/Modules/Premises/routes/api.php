<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Modules\Premises\Http\Controllers\PremisesController;
use Illuminate\Support\Facades\Route;

Route::prefix((string)config('workcore.premises.route_prefix'))->middleware((array)config('workcore.premises.middleware'))->name('api.workcore.v1.')->group(function():void{
    Route::get('premises',[PremisesController::class,'index'])->name('premises.index');
    Route::post('premises',[PremisesController::class,'store'])->name('premises.store');
    Route::get('premises/{premises}',[PremisesController::class,'show'])->name('premises.show');
    Route::get('premises/{premises}/readiness',[PremisesController::class,'readiness'])->name('premises.readiness.show');
    Route::post('premises/{premises}/approve',[PremisesController::class,'approve'])->name('premises.approve');
    Route::post('premises/{premises}/archive',[PremisesController::class,'archive'])->name('premises.archive');
    Route::post('premises/{premises}/spaces',[PremisesController::class,'storeSpace'])->name('premises.spaces.store');
    Route::post('premises/{premises}/hazards',[PremisesController::class,'storeHazard'])->name('premises.hazards.store');
    Route::post('premises/{premises}/hazards/{hazard}/resolve',[PremisesController::class,'resolveHazard'])->name('premises.hazards.resolve');
    Route::post('premises/{premises}/access-points',[PremisesController::class,'storeAccessPoint'])->name('premises.access-points.store');
    Route::post('premises/{premises}/service-windows',[PremisesController::class,'storeServiceWindow'])->name('premises.service-windows.store');
    Route::post('premises/{premises}/service-plans',[PremisesController::class,'storeServicePlan'])->name('premises.service-plans.store');
    Route::post('premises/{premises}/meter-readings',[PremisesController::class,'recordMeterReading'])->name('premises.meter-readings.store');
    Route::post('premises/{premises}/identifiers',[PremisesController::class,'registerIdentifier'])->name('premises.identifiers.store');
    Route::post('premises/{premises}/contacts',[PremisesController::class,'linkContact'])->name('premises.contacts.link');
    Route::post('premises/{premises}/documents',[PremisesController::class,'linkDocument'])->name('premises.documents.link');
});

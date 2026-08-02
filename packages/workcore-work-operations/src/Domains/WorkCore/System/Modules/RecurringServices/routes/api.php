<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\RecurringServices\Http\Controllers\RecurringServiceController;
use Illuminate\Support\Facades\Route;
Route::prefix((string)config('workcore.recurring.route_prefix'))->middleware((array)config('workcore.recurring.middleware'))->name('api.workcore.v1.')->group(function():void{
 Route::get('service-agreements',[RecurringServiceController::class,'index'])->name('service-agreements.index');
 Route::post('service-agreements',[RecurringServiceController::class,'store'])->name('service-agreements.store');
 Route::post('service-agreements/generate',[RecurringServiceController::class,'generateAll'])->name('service-agreements.generate-all');
 Route::post('service-agreements/{agreement}/status',[RecurringServiceController::class,'status'])->name('service-agreements.status');
 Route::post('service-agreements/{agreement}/generate',[RecurringServiceController::class,'generate'])->name('service-agreements.generate');
 Route::post('service-agreements/{agreement}/renew',[RecurringServiceController::class,'renew'])->name('service-agreements.renew');
 Route::post('recurring-occurrences/{occurrence}/skip',[RecurringServiceController::class,'skip'])->name('recurring-occurrences.skip');
 Route::post('recurring-occurrences/{occurrence}/reschedule',[RecurringServiceController::class,'reschedule'])->name('recurring-occurrences.reschedule');
});

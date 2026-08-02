<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\Reviews\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;
Route::prefix((string)config('workcore.reviews.route_prefix'))->middleware((array)config('workcore.reviews.middleware'))->name('api.workcore.v1.')->group(function():void{
 Route::get('reviews',[ReviewController::class,'index'])->name('reviews.index');
 Route::post('review-requests',[ReviewController::class,'createRequest'])->name('review-requests.store');
 Route::post('reviews',[ReviewController::class,'record'])->name('reviews.store');
 Route::post('reviews/{review}/publication',[ReviewController::class,'publish'])->name('reviews.publication');
 Route::post('reviews/{review}/service-recovery',[ReviewController::class,'recovery'])->name('reviews.recovery');
 Route::post('rebooking-requests',[ReviewController::class,'rebook'])->name('rebooking-requests.store');
});

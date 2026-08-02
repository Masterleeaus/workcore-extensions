<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\Workforce\Http\Controllers\WorkerController;
use Illuminate\Support\Facades\Route;
Route::prefix((string)config('workcore.workforce.route_prefix'))->middleware((array)config('workcore.workforce.middleware'))->name('api.workcore.v1.')->group(function():void{
 Route::get('workers',[WorkerController::class,'index'])->name('workers.index'); Route::post('workers',[WorkerController::class,'store'])->name('workers.store');
 Route::post('skills',[WorkerController::class,'storeSkill'])->name('skills.store'); Route::post('workers/{worker}/skills',[WorkerController::class,'assignSkill'])->name('workers.skills.store'); Route::post('workers/{worker}/certifications',[WorkerController::class,'addCertification'])->name('workers.certifications.store');
});

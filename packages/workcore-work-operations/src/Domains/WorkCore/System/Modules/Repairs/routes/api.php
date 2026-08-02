<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\Repairs\Http\Controllers\RepairController;
use Illuminate\Support\Facades\Route;
Route::prefix((string)config('workcore.repairs.route_prefix'))->middleware((array)config('workcore.repairs.middleware'))->name('api.workcore.v1.')->group(function():void{
    Route::get('repairs',[RepairController::class,'index'])->name('repairs.index');
    Route::post('repair-templates',[RepairController::class,'createTemplate'])->name('repair-templates.store');
    Route::post('repairs',[RepairController::class,'report'])->name('repairs.store');
    Route::post('repairs/{repair}/start',[RepairController::class,'start'])->name('repairs.start');
    Route::post('repairs/{repair}/complete',[RepairController::class,'complete'])->name('repairs.complete');
});

<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\Wizards\Http\Controllers\WizardController;
use Illuminate\Support\Facades\Route;
Route::prefix((string)config('workcore.wizards.api_prefix','api/workcore/v1'))->middleware((array)config('workcore.wizards.api_middleware'))->name('api.workcore.v1.')->group(function():void{
Route::get('wizards',[WizardController::class,'index']);Route::post('wizards/definitions',[WizardController::class,'createDefinition']);Route::post('wizards/definitions/{definition}/publish',[WizardController::class,'publishDefinition']);Route::post('wizards/runs',[WizardController::class,'start']);Route::get('wizards/runs/{run}',[WizardController::class,'show']);Route::get('wizards/runs/{run}/next',[WizardController::class,'next']);Route::put('wizards/runs/{run}/answer',[WizardController::class,'answer']);Route::post('wizards/runs/{run}/approve',[WizardController::class,'approve']);Route::post('wizards/runs/{run}/pause',[WizardController::class,'pause']);Route::post('wizards/runs/{run}/resume',[WizardController::class,'resume']);Route::post('wizards/runs/{run}/complete',[WizardController::class,'complete']);});

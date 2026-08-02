<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\Wizards\Http\Controllers\WizardPageController;
use Illuminate\Support\Facades\Route;
Route::prefix((string)config('workcore.wizards.web_prefix','workcore/wizards'))->middleware((array)config('workcore.wizards.web_middleware'))->name('workcore.wizards.')->group(function():void{Route::get('/',[WizardPageController::class,'index'])->name('index');Route::get('/{wizard}',[WizardPageController::class,'run'])->name('run');});

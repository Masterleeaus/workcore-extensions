<?php

declare(strict_types=1);
use App\Domains\WorkCore\System\Modules\Territories\Http\Controllers\TerritoryController;
use Illuminate\Support\Facades\Route;
Route::prefix((string)config('workcore.territories.route_prefix'))->middleware((array)config('workcore.territories.middleware'))->name('api.workcore.v1.')->group(function():void{
 Route::get('territories',[TerritoryController::class,'index'])->name('territories.index');
 Route::get('territories/match',[TerritoryController::class,'match'])->name('territories.match');
 Route::post('regions',[TerritoryController::class,'storeRegion'])->name('regions.store');
 Route::post('districts',[TerritoryController::class,'storeDistrict'])->name('districts.store');
 Route::post('branches',[TerritoryController::class,'storeBranch'])->name('branches.store');
 Route::post('territories',[TerritoryController::class,'storeTerritory'])->name('territories.store');
 Route::post('territories/{territory}/assignments',[TerritoryController::class,'assign'])->name('territories.assignments.store');
});

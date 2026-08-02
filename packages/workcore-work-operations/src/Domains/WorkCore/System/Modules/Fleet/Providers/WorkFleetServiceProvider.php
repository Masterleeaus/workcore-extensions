<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Fleet\Providers;
use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Modules\Fleet\Actions\{AssignFleetVehicle,CreateFleetVehicle,RecordFleetMileage,ReturnFleetVehicle,SearchFleetVehicles};
use App\Domains\WorkCore\System\Modules\Fleet\Contracts\FleetRepositoryContract;
use App\Domains\WorkCore\System\Modules\Fleet\Repositories\EloquentFleetRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;
final class WorkFleetServiceProvider extends ServiceProvider
{
    public function register():void
    {
        $this->app->bind(FleetRepositoryContract::class,EloquentFleetRepository::class);$actions=$this->app->make(BusinessActionRegistry::class);
        foreach([
            ['workcore.fleet.vehicle.create',CreateFleetVehicle::class,'medium','manage'],['workcore.fleet.mileage.record',RecordFleetMileage::class,'low','mileage'],
            ['workcore.fleet.vehicle.assign',AssignFleetVehicle::class,'high','assign'],['workcore.fleet.vehicle.return',ReturnFleetVehicle::class,'high','assign'],
        ] as[$key,$handler,$risk,$permission]){$actions->register(new ActionDefinition($key,$handler,$risk,true,'workcore.fleet',(string)config("workcore.fleet.permissions.{$permission}"),['domain'=>'fleet','asset_authority'=>'workcore.assets']));}
        $this->app->make(ReadModelRegistry::class)->register(new ReadModelDefinition('workcore.fleet.search',SearchFleetVehicles::class,'workcore.fleet',permission:(string)config('workcore.fleet.permissions.view')));
    }
    public function boot():void{if((bool)config('workcore.fleet.routes_enabled',false)){$this->loadRoutesFrom(__DIR__.'/../routes/api.php');}}
}

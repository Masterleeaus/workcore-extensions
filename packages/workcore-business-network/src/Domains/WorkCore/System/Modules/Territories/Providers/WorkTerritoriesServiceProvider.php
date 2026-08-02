<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Territories\Providers;
use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\TerritoryAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\TerritoryAccessAdapter;
use App\Domains\WorkCore\System\Modules\Territories\Actions\{AssignTerritory,CreateBranch,CreateDistrict,CreateRegion,CreateTerritory,MatchTerritories,SearchTerritories};
use App\Domains\WorkCore\System\Modules\Territories\Contracts\TerritoryRepositoryContract;
use App\Domains\WorkCore\System\Modules\Territories\Geometry\{GeoJsonCentroid,PointInPolygon,PolygonGeoJson};
use App\Domains\WorkCore\System\Modules\Territories\Repositories\EloquentTerritoryRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;
final class WorkTerritoriesServiceProvider extends ServiceProvider
{
 public function register():void
 {
  $this->app->singleton(PolygonGeoJson::class);$this->app->singleton(PointInPolygon::class);$this->app->singleton(GeoJsonCentroid::class);
  $this->app->bind(TerritoryRepositoryContract::class,EloquentTerritoryRepository::class);$this->app->bind(TerritoryAccessContract::class,TerritoryAccessAdapter::class);
  $a=$this->app->make(BusinessActionRegistry::class);
  foreach([
   new ActionDefinition('workcore.region.create',CreateRegion::class,'medium',true,'workcore.territories',(string)config('workcore.territories.permissions.manage')),
   new ActionDefinition('workcore.district.create',CreateDistrict::class,'medium',true,'workcore.territories',(string)config('workcore.territories.permissions.manage')),
   new ActionDefinition('workcore.branch.create',CreateBranch::class,'medium',true,'workcore.territories',(string)config('workcore.territories.permissions.manage')),
   new ActionDefinition('workcore.territory.create',CreateTerritory::class,'medium',true,'workcore.territories',(string)config('workcore.territories.permissions.manage')),
   new ActionDefinition('workcore.territory.assign',AssignTerritory::class,'medium',true,'workcore.territories',(string)config('workcore.territories.permissions.assign')),
  ] as $d){$a->register($d);} $r=$this->app->make(ReadModelRegistry::class);$view=(string)config('workcore.territories.permissions.view');$r->register(new ReadModelDefinition('workcore.territory.search',SearchTerritories::class,'workcore.territories',permission:$view));$r->register(new ReadModelDefinition('workcore.territory.match',MatchTerritories::class,'workcore.territories',permission:$view));
 }
 public function boot():void{if(config('workcore.territories.routes_enabled',false))$this->loadRoutesFrom(__DIR__.'/../routes/api.php');}
}

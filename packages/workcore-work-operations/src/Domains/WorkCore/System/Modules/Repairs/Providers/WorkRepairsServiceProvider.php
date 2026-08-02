<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Repairs\Providers;
use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Modules\Repairs\Actions\{CompleteRepairOrder,CreateRepairTemplate,ReportRepairOrder,SearchRepairOrders,StartRepairOrder};
use App\Domains\WorkCore\System\Modules\Repairs\Contracts\RepairRepositoryContract;
use App\Domains\WorkCore\System\Modules\Repairs\Repositories\EloquentRepairRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;
final class WorkRepairsServiceProvider extends ServiceProvider
{
    public function register():void
    {
        $this->app->bind(RepairRepositoryContract::class,EloquentRepairRepository::class);$actions=$this->app->make(BusinessActionRegistry::class);
        foreach([
            ['workcore.repairs.template.create',CreateRepairTemplate::class,'low','manage_templates'],['workcore.repairs.order.report',ReportRepairOrder::class,'medium','report'],
            ['workcore.repairs.order.start',StartRepairOrder::class,'high','manage'],['workcore.repairs.order.complete',CompleteRepairOrder::class,'critical','return_to_service'],
        ] as[$key,$handler,$risk,$permission]){$actions->register(new ActionDefinition($key,$handler,$risk,true,'workcore.repairs',(string)config("workcore.repairs.permissions.{$permission}"),['domain'=>'repairs','maintenance_authority'=>'workcore.assets','job_authority'=>'workcore.operations']));}
        $this->app->make(ReadModelRegistry::class)->register(new ReadModelDefinition('workcore.repairs.search',SearchRepairOrders::class,'workcore.repairs',permission:(string)config('workcore.repairs.permissions.view')));
    }
    public function boot():void{if((bool)config('workcore.repairs.routes_enabled',false)){$this->loadRoutesFrom(__DIR__.'/../routes/api.php');}}
}

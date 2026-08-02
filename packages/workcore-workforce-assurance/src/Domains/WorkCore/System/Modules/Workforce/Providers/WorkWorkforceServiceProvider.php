<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Workforce\Providers;
use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\Adapters\WorkerAccessAdapter;
use App\Domains\WorkCore\System\Contracts\Records\WorkerAccessContract;
use App\Domains\WorkCore\System\Modules\Workforce\Actions\{AddWorkerCertification,AssignWorkerSkill,CreateSkill,CreateWorker,SearchWorkers};
use App\Domains\WorkCore\System\Modules\Workforce\Contracts\WorkerRepositoryContract;
use App\Domains\WorkCore\System\Modules\Workforce\Repositories\EloquentWorkerRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;
final class WorkWorkforceServiceProvider extends ServiceProvider
{
 public function register(): void
 {
  $this->app->bind(WorkerRepositoryContract::class,EloquentWorkerRepository::class); $this->app->bind(WorkerAccessContract::class,WorkerAccessAdapter::class);
  $a=$this->app->make(BusinessActionRegistry::class);
  $a->register(new ActionDefinition('workcore.worker.create',CreateWorker::class,'medium',true,'workcore.workforce',(string)config('workcore.workforce.permissions.manage')));
  $a->register(new ActionDefinition('workcore.skill.create',CreateSkill::class,'low',true,'workcore.workforce',(string)config('workcore.workforce.permissions.manage')));
  $a->register(new ActionDefinition('workcore.worker.assign_skill',AssignWorkerSkill::class,'medium',true,'workcore.workforce',(string)config('workcore.workforce.permissions.skills')));
  $a->register(new ActionDefinition('workcore.worker.add_certification',AddWorkerCertification::class,'medium',true,'workcore.workforce',(string)config('workcore.workforce.permissions.certifications')));
  $this->app->make(ReadModelRegistry::class)->register(new ReadModelDefinition('workcore.worker.search',SearchWorkers::class,'workcore.workforce', permission: (string) config('workcore.workforce.permissions.view')));
 }
 public function boot(): void {if((bool)config('workcore.workforce.routes_enabled',false))$this->loadRoutesFrom(__DIR__.'/../routes/api.php');}
}

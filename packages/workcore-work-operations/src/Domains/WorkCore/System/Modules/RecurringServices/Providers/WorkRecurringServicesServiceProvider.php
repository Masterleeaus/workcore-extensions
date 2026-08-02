<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\RecurringServices\Providers;
use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\RecurringServiceAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\RecurringServiceAccessAdapter;
use App\Domains\WorkCore\System\Modules\RecurringServices\Actions\{ChangeServiceAgreementStatus,CreateServiceAgreement,GenerateRecurringOccurrences,RenewServiceAgreement,RescheduleRecurringOccurrence,SearchServiceAgreements,SkipRecurringOccurrence};
use App\Domains\WorkCore\System\Modules\RecurringServices\Contracts\RecurringServiceRepositoryContract;
use App\Domains\WorkCore\System\Modules\RecurringServices\Repositories\EloquentRecurringServiceRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;
final class WorkRecurringServicesServiceProvider extends ServiceProvider
{
 public function register(): void{
  $this->app->bind(RecurringServiceRepositoryContract::class,EloquentRecurringServiceRepository::class);$this->app->bind(RecurringServiceAccessContract::class,RecurringServiceAccessAdapter::class);
  $a=$this->app->make(BusinessActionRegistry::class);$p=(array)config('workcore.recurring.permissions');
  $a->register(new ActionDefinition('workcore.recurring.agreement.create',CreateServiceAgreement::class,'high',true,'workcore.recurring',(string)$p['manage']));
  $a->register(new ActionDefinition('workcore.recurring.agreement.change_status',ChangeServiceAgreementStatus::class,'high',true,'workcore.recurring',(string)$p['status']));
  $a->register(new ActionDefinition('workcore.recurring.generate',GenerateRecurringOccurrences::class,'critical',true,'workcore.recurring',(string)$p['generate']));
  $a->register(new ActionDefinition('workcore.recurring.occurrence.skip',SkipRecurringOccurrence::class,'high',true,'workcore.recurring',(string)$p['exceptions']));
  $a->register(new ActionDefinition('workcore.recurring.occurrence.reschedule',RescheduleRecurringOccurrence::class,'high',true,'workcore.recurring',(string)$p['exceptions']));
  $a->register(new ActionDefinition('workcore.recurring.agreement.renew',RenewServiceAgreement::class,'high',true,'workcore.recurring',(string)$p['renew']));
  $this->app->make(ReadModelRegistry::class)->register(new ReadModelDefinition('workcore.recurring.search',SearchServiceAgreements::class,'workcore.recurring',permission:(string)$p['view']));
 }
 public function boot(): void{if((bool)config('workcore.recurring.routes_enabled',false))$this->loadRoutesFrom(__DIR__.'/../routes/api.php');}
}

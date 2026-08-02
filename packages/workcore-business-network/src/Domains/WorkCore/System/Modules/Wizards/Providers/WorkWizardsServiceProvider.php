<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Wizards\Providers;
use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Modules\Wizards\Actions\{ApproveWizardSection,CompleteWizardRun,CreateWizardDefinition,PauseWizardRun,PublishWizardDefinition,ResumeWizardRun,SaveWizardAnswer,StartWizardRun};
use App\Domains\WorkCore\System\Modules\Wizards\Contracts\WizardRepositoryContract;
use App\Domains\WorkCore\System\Modules\Wizards\ReadModels\{GetNextWizardQuestion,GetWizardRun,ListWizardDefinitions};
use App\Domains\WorkCore\System\Modules\Wizards\Repositories\EloquentWizardRepository;
use App\Domains\WorkCore\System\Modules\Wizards\Services\{WizardAnswerValidator,WizardBranchEvaluator,WizardDefinitionRegistry,WizardRiskPolicy,WizardRuntime};
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;
final class WorkWizardsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WizardDefinitionRegistry::class); $this->app->singleton(WizardBranchEvaluator::class); $this->app->singleton(WizardRiskPolicy::class); $this->app->singleton(WizardAnswerValidator::class); $this->app->bind(WizardRepositoryContract::class,EloquentWizardRepository::class); $this->app->scoped(WizardRuntime::class);
        $a=$this->app->make(BusinessActionRegistry::class); foreach([
          new ActionDefinition('workcore.wizard_definition.create',CreateWizardDefinition::class,'medium',true,'workcore.wizards',(string)config('workcore.wizards.permissions.design')),
          new ActionDefinition('workcore.wizard_definition.publish',PublishWizardDefinition::class,'high',true,'workcore.wizards',(string)config('workcore.wizards.permissions.publish')),
          new ActionDefinition('workcore.wizard_run.start',StartWizardRun::class,'low',false,'workcore.wizards',(string)config('workcore.wizards.permissions.use')),
          new ActionDefinition('workcore.wizard_answer.save',SaveWizardAnswer::class,'low',false,'workcore.wizards',(string)config('workcore.wizards.permissions.use')),
          new ActionDefinition('workcore.wizard_section.approve',ApproveWizardSection::class,'high',true,'workcore.wizards',(string)config('workcore.wizards.permissions.approve')),
          new ActionDefinition('workcore.wizard_run.pause',PauseWizardRun::class,'low',false,'workcore.wizards',(string)config('workcore.wizards.permissions.use')),
          new ActionDefinition('workcore.wizard_run.resume',ResumeWizardRun::class,'low',false,'workcore.wizards',(string)config('workcore.wizards.permissions.use')),
          new ActionDefinition('workcore.wizard_run.complete',CompleteWizardRun::class,'high',true,'workcore.wizards',(string)config('workcore.wizards.permissions.approve')),
        ] as $d)$a->register($d);
        $r=$this->app->make(ReadModelRegistry::class);$view=(string)config('workcore.wizards.permissions.view');$r->register(new ReadModelDefinition('workcore.wizard.list',ListWizardDefinitions::class,'workcore.wizards',permission:$view));$r->register(new ReadModelDefinition('workcore.wizard.run',GetWizardRun::class,'workcore.wizards',permission:$view));$r->register(new ReadModelDefinition('workcore.wizard.next_question',GetNextWizardQuestion::class,'workcore.wizards',permission:$view));
    }
    public function boot(): void { $this->loadViewsFrom(__DIR__.'/../resources/views','workcore-wizards'); if((bool)config('workcore.wizards.routes_enabled',false)){$this->loadRoutesFrom(__DIR__.'/../routes/api.php');$this->loadRoutesFrom(__DIR__.'/../routes/web.php');} }
}

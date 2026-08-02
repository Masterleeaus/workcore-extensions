<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Forms\Providers;
use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\{FormSubmissionAccessContract,FormTemplateAccessContract};
use App\Domains\WorkCore\System\Contracts\Records\Adapters\{FormSubmissionAccessAdapter,FormTemplateAccessAdapter};
use App\Domains\WorkCore\System\Modules\Forms\Actions\{ConvertFormFailureToWorkOrder,CreateFormTemplate,PublishFormTemplate,SaveFormResponse,SearchFormSubmissions,SearchFormTemplates,StartFormSubmission,SubmitFormSubmission};
use App\Domains\WorkCore\System\Modules\Forms\Contracts\FormRepositoryContract;
use App\Domains\WorkCore\System\Modules\Forms\Repositories\EloquentFormRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;
final class WorkFormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FormRepositoryContract::class,EloquentFormRepository::class);
        $this->app->bind(FormTemplateAccessContract::class,FormTemplateAccessAdapter::class);
        $this->app->bind(FormSubmissionAccessContract::class,FormSubmissionAccessAdapter::class);
        $actions=$this->app->make(BusinessActionRegistry::class);
        $actions->register(new ActionDefinition('workcore.form_template.create',CreateFormTemplate::class,'medium',true,'workcore.forms',(string)config('workcore.forms.permissions.manage_templates')));
        $actions->register(new ActionDefinition('workcore.form_template.publish',PublishFormTemplate::class,'high',true,'workcore.forms',(string)config('workcore.forms.permissions.publish_templates')));
        $actions->register(new ActionDefinition('workcore.form_submission.start',StartFormSubmission::class,'low',true,'workcore.forms',(string)config('workcore.forms.permissions.complete')));
        $actions->register(new ActionDefinition('workcore.form_response.save',SaveFormResponse::class,'low',true,'workcore.forms',(string)config('workcore.forms.permissions.complete')));
        $actions->register(new ActionDefinition('workcore.form_submission.submit',SubmitFormSubmission::class,'medium',true,'workcore.forms',(string)config('workcore.forms.permissions.submit')));
        $actions->register(new ActionDefinition('workcore.form_failure.convert_to_work_order',ConvertFormFailureToWorkOrder::class,'high',true,'workcore.forms',(string)config('workcore.forms.permissions.convert_failures')));
        $read=$this->app->make(ReadModelRegistry::class);
        $read->register(new ReadModelDefinition('workcore.form_template.search',SearchFormTemplates::class,'workcore.forms', permission: (string) config('workcore.forms.permissions.view')));
        $read->register(new ReadModelDefinition('workcore.form_submission.search',SearchFormSubmissions::class,'workcore.forms', permission: (string) config('workcore.forms.permissions.view')));
    }
    public function boot(): void { if((bool)config('workcore.forms.routes_enabled',false))$this->loadRoutesFrom(__DIR__.'/../routes/api.php'); }
}

<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Catalogue\Providers;
use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\ServiceAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\ServiceAccessAdapter;
use App\Domains\WorkCore\System\Modules\Catalogue\Actions\{CreateJobTemplate,CreateService,CreateServiceCategory,ListServiceFaqs,ListServiceVariants,SearchServices};
use App\Domains\WorkCore\System\Modules\Catalogue\Contracts\ServiceCatalogueRepositoryContract;
use App\Domains\WorkCore\System\Modules\Catalogue\Repositories\EloquentServiceCatalogueRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;
final class WorkCatalogueServiceProvider extends ServiceProvider{public function register():void{$this->app->bind(ServiceCatalogueRepositoryContract::class,EloquentServiceCatalogueRepository::class);$this->app->bind(ServiceAccessContract::class,ServiceAccessAdapter::class);$a=$this->app->make(BusinessActionRegistry::class);$a->register(new ActionDefinition('workcore.service_category.create',CreateServiceCategory::class,'low',true,'workcore.catalogue',(string)config('workcore.catalogue.permissions.manage')));$a->register(new ActionDefinition('workcore.service.create',CreateService::class,'medium',true,'workcore.catalogue',(string)config('workcore.catalogue.permissions.manage')));$a->register(new ActionDefinition('workcore.job_template.create',CreateJobTemplate::class,'medium',true,'workcore.catalogue',(string)config('workcore.catalogue.permissions.manage')));$reads=$this->app->make(ReadModelRegistry::class);
$reads->register(new ReadModelDefinition('workcore.service.search',SearchServices::class,'workcore.catalogue',[],(string)config('workcore.catalogue.permissions.view')));
$reads->register(new ReadModelDefinition('workcore.service_variant.list',ListServiceVariants::class,'workcore.catalogue',[],(string)config('workcore.catalogue.permissions.view')));
$reads->register(new ReadModelDefinition('workcore.service_faq.list',ListServiceFaqs::class,'workcore.catalogue',[],(string)config('workcore.catalogue.permissions.view')));}public function boot():void{if(config('workcore.catalogue.routes_enabled',false))$this->loadRoutesFrom(__DIR__.'/../routes/api.php');}}

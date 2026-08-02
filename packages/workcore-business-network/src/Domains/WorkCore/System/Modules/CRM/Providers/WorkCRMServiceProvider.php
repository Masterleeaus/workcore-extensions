<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Providers;

use App\Domains\WorkCore\System\Actions\ActionDefinition;
use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use App\Domains\WorkCore\System\Contracts\Records\ContactAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\CustomerAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\LeadAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\OpportunityAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\SalesPipelineAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\ContactAccessAdapter;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\CustomerAccessAdapter;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\LeadAccessAdapter;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\OpportunityAccessAdapter;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\SalesPipelineAccessAdapter;
use App\Domains\WorkCore\System\Modules\CRM\Actions\ConvertLeadToCustomer;
use App\Domains\WorkCore\System\Modules\CRM\Actions\CreateContact;
use App\Domains\WorkCore\System\Modules\CRM\Actions\CreateCrmActivity;
use App\Domains\WorkCore\System\Modules\CRM\Actions\CreateCustomer;
use App\Domains\WorkCore\System\Modules\CRM\Actions\CreateLead;
use App\Domains\WorkCore\System\Modules\CRM\Actions\CreateOpportunity;
use App\Domains\WorkCore\System\Modules\CRM\Actions\CreateSalesPipeline;
use App\Domains\WorkCore\System\Modules\CRM\Actions\GetCrmForecast;
use App\Domains\WorkCore\System\Modules\CRM\Actions\ListCrmActivities;
use App\Domains\WorkCore\System\Modules\CRM\Actions\ListSalesPipelines;
use App\Domains\WorkCore\System\Modules\CRM\Actions\MarkOpportunityLost;
use App\Domains\WorkCore\System\Modules\CRM\Actions\MarkOpportunityWon;
use App\Domains\WorkCore\System\Modules\CRM\Actions\MoveOpportunity;
use App\Domains\WorkCore\System\Modules\CRM\Actions\SearchCustomers;
use App\Domains\WorkCore\System\Modules\CRM\Actions\SearchLeads;
use App\Domains\WorkCore\System\Modules\CRM\Actions\SearchOpportunities;
use App\Domains\WorkCore\System\Modules\CRM\Actions\SetPrimaryContact;
use App\Domains\WorkCore\System\Modules\CRM\Actions\UpdateLead;
use App\Domains\WorkCore\System\Modules\CRM\Actions\UpdateOpportunity;
use App\Domains\WorkCore\System\Modules\CRM\Actions\ViewSalesPipelineBoard;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\ContactRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\CrmActivityRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\CustomerRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\LeadRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\OpportunityRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\SalesPipelineRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Repositories\EloquentContactRepository;
use App\Domains\WorkCore\System\Modules\CRM\Repositories\EloquentCrmActivityRepository;
use App\Domains\WorkCore\System\Modules\CRM\Repositories\EloquentCustomerRepository;
use App\Domains\WorkCore\System\Modules\CRM\Repositories\EloquentLeadRepository;
use App\Domains\WorkCore\System\Modules\CRM\Repositories\EloquentOpportunityRepository;
use App\Domains\WorkCore\System\Modules\CRM\Repositories\EloquentSalesPipelineRepository;
use App\Domains\WorkCore\System\ReadModels\ReadModelDefinition;
use App\Domains\WorkCore\System\ReadModels\ReadModelRegistry;
use Illuminate\Support\ServiceProvider;

final class WorkCRMServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerRepositoryContract::class, EloquentCustomerRepository::class);
        $this->app->bind(ContactRepositoryContract::class, EloquentContactRepository::class);
        $this->app->bind(LeadRepositoryContract::class, EloquentLeadRepository::class);
        $this->app->bind(SalesPipelineRepositoryContract::class, EloquentSalesPipelineRepository::class);
        $this->app->bind(OpportunityRepositoryContract::class, EloquentOpportunityRepository::class);
        $this->app->bind(CrmActivityRepositoryContract::class, EloquentCrmActivityRepository::class);

        $this->app->bind(CustomerAccessContract::class, CustomerAccessAdapter::class);
        $this->app->bind(ContactAccessContract::class, ContactAccessAdapter::class);
        $this->app->bind(LeadAccessContract::class, LeadAccessAdapter::class);
        $this->app->bind(SalesPipelineAccessContract::class, SalesPipelineAccessAdapter::class);
        $this->app->bind(OpportunityAccessContract::class, OpportunityAccessAdapter::class);

        $actions = $this->app->make(BusinessActionRegistry::class);
        foreach ([
            new ActionDefinition('workcore.customer.create', CreateCustomer::class, 'medium', true, 'workcore.crm', (string) config('workcore.crm.permissions.customers.create')),
            new ActionDefinition('workcore.contact.create', CreateContact::class, 'medium', true, 'workcore.crm', (string) config('workcore.crm.permissions.contacts.create')),
            new ActionDefinition('workcore.contact.set_primary', SetPrimaryContact::class, 'medium', true, 'workcore.crm', (string) config('workcore.crm.permissions.contacts.update')),
            new ActionDefinition('workcore.lead.create', CreateLead::class, 'medium', true, 'workcore.crm', (string) config('workcore.crm.permissions.leads.create')),
            new ActionDefinition('workcore.lead.update', UpdateLead::class, 'medium', true, 'workcore.crm', (string) config('workcore.crm.permissions.leads.update')),
            new ActionDefinition('workcore.lead.convert', ConvertLeadToCustomer::class, 'high', true, 'workcore.crm', (string) config('workcore.crm.permissions.leads.convert')),
            new ActionDefinition('workcore.sales_pipeline.create', CreateSalesPipeline::class, 'medium', true, 'workcore.crm', (string) config('workcore.crm.permissions.pipelines.manage')),
            new ActionDefinition('workcore.opportunity.create', CreateOpportunity::class, 'medium', true, 'workcore.crm', (string) config('workcore.crm.permissions.opportunities.create')),
            new ActionDefinition('workcore.opportunity.update', UpdateOpportunity::class, 'medium', true, 'workcore.crm', (string) config('workcore.crm.permissions.opportunities.update')),
            new ActionDefinition('workcore.opportunity.move', MoveOpportunity::class, 'low', false, 'workcore.crm', (string) config('workcore.crm.permissions.opportunities.move')),
            new ActionDefinition('workcore.opportunity.mark_won', MarkOpportunityWon::class, 'high', true, 'workcore.crm', (string) config('workcore.crm.permissions.opportunities.close')),
            new ActionDefinition('workcore.opportunity.mark_lost', MarkOpportunityLost::class, 'high', true, 'workcore.crm', (string) config('workcore.crm.permissions.opportunities.close')),
            new ActionDefinition('workcore.crm_activity.create', CreateCrmActivity::class, 'low', false, 'workcore.crm', (string) config('workcore.crm.permissions.activities.create')),
        ] as $definition) {
            $actions->register($definition);
        }

        $read = $this->app->make(ReadModelRegistry::class);
        $read->register(new ReadModelDefinition('workcore.customer.search', SearchCustomers::class, 'workcore.crm', [], (string) config('workcore.crm.permissions.customers.view')));
        $read->register(new ReadModelDefinition('workcore.lead.search', SearchLeads::class, 'workcore.crm', [], (string) config('workcore.crm.permissions.leads.view')));
        $read->register(new ReadModelDefinition('workcore.sales_pipeline.list', ListSalesPipelines::class, 'workcore.crm', [], (string) config('workcore.crm.permissions.pipelines.view')));
        $read->register(new ReadModelDefinition('workcore.sales_pipeline.board', ViewSalesPipelineBoard::class, 'workcore.crm', [], (string) config('workcore.crm.permissions.pipelines.view')));
        $read->register(new ReadModelDefinition('workcore.opportunity.search', SearchOpportunities::class, 'workcore.crm', [], (string) config('workcore.crm.permissions.opportunities.view')));
        $read->register(new ReadModelDefinition('workcore.crm.forecast', GetCrmForecast::class, 'workcore.crm', [], (string) config('workcore.crm.permissions.opportunities.view')));
        $read->register(new ReadModelDefinition('workcore.crm_activity.list', ListCrmActivities::class, 'workcore.crm', [], (string) config('workcore.crm.permissions.activities.view')));
    }

    public function boot(): void
    {
        if (config('workcore.crm.routes_enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }
}

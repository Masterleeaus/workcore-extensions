<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Providers;

use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\PremisesAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\PremisesAccessAdapter;
use App\Domains\WorkCore\System\Modules\Premises\Actions\{
    AddPremisesHazard,
    ApprovePremises,
    ArchivePremises,
    CreatePremises,
    CreatePremisesAccessPoint,
    CreatePremisesServicePlan,
    CreatePremisesServiceWindow,
    CreatePremisesSpace,
    GetPremisesProfile,
    GetPremisesReadiness,
    LinkPremisesContact,
    LinkPremisesDocument,
    RecordPremisesMeterReading,
    RegisterPremisesIdentifier,
    ResolvePremisesHazard,
    SearchPremises
};
use App\Domains\WorkCore\System\Modules\Premises\Contracts\PremisesRepositoryContract;
use App\Domains\WorkCore\System\Modules\Premises\Repositories\EloquentPremisesRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;

final class WorkPremisesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ([
            'config' => 'managedpremises',
            'portal' => 'managedpremises_portal',
            'operations' => 'managedpremises_operations',
            'premises_profiles' => 'managedpremises_profiles',
            'generative_panels' => 'managedpremises_generative_panels',
            'compliance_rule_packs' => 'managedpremises_compliance_rule_packs',
            'permissions' => 'managedpremises_permissions',
        ] as $file => $key) {
            $path = __DIR__ . "/../Config/{$file}.php";
            if (is_file($path)) {
                $this->mergeConfigFrom($path, $key);
            }
        }

        $this->app->bind(PremisesRepositoryContract::class,EloquentPremisesRepository::class);
        $this->app->bind(PremisesAccessContract::class,PremisesAccessAdapter::class);
        $actions=$this->app->make(BusinessActionRegistry::class);
        $definitions=[
            ['workcore.premises.create',CreatePremises::class,'medium','create'],
            ['workcore.premises.approve',ApprovePremises::class,'high','approve'],
            ['workcore.premises.archive',ArchivePremises::class,'critical','archive'],
            ['workcore.premises.space.create',CreatePremisesSpace::class,'medium','manage_spaces'],
            ['workcore.premises.hazard.report',AddPremisesHazard::class,'high','manage_hazards'],
            ['workcore.premises.access_point.create',CreatePremisesAccessPoint::class,'high','manage_access'],
            ['workcore.premises.service_window.create',CreatePremisesServiceWindow::class,'medium','manage_service_plans'],
            ['workcore.premises.service_plan.create',CreatePremisesServicePlan::class,'medium','manage_service_plans'],
            ['workcore.premises.meter_reading.record',RecordPremisesMeterReading::class,'low','record_meter_readings'],
            ['workcore.premises.identifier.register',RegisterPremisesIdentifier::class,'medium','manage_identifiers'],
            ['workcore.premises.contact.link',LinkPremisesContact::class,'medium','manage_contacts'],
            ['workcore.premises.document.link',LinkPremisesDocument::class,'medium','manage_documents'],
            ['workcore.premises.hazard.resolve',ResolvePremisesHazard::class,'high','manage_hazards'],
        ];
        foreach($definitions as [$key,$handler,$risk,$permission]){
            $actions->register(new ActionDefinition($key,$handler,$risk,true,'workcore.premises',(string)config("workcore.premises.permissions.{$permission}"),['domain'=>'premises_operations']));
        }
        $reads=$this->app->make(ReadModelRegistry::class);
        $view=(string)config('workcore.premises.permissions.view');
        $reads->register(new ReadModelDefinition('workcore.premises.search',SearchPremises::class,'workcore.premises',permission:$view));
        $reads->register(new ReadModelDefinition('workcore.premises.profile',GetPremisesProfile::class,'workcore.premises',permission:$view));
        $reads->register(new ReadModelDefinition('workcore.premises.readiness',GetPremisesReadiness::class,'workcore.premises',permission:$view));
    }

    public function boot(): void
    {
        if(config('workcore.premises.routes_enabled',false)){$this->loadRoutesFrom(__DIR__.'/../routes/api.php');}
    }
}

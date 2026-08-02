<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assurance\Providers;

use App\Domains\WorkCore\System\Actions\{ActionDefinition, BusinessActionRegistry};
use App\Domains\WorkCore\System\Modules\Assurance\Actions\{
    CompleteInspection,
    CreateCorrectiveAction,
    CreateFinding,
    CreateInspectionTemplate,
    GetAssuranceSummary,
    RecordIncident,
    RecordInspectionResult,
    RecordRisk,
    SearchFindings,
    SearchIncidents,
    SearchInspections,
    SearchRisks,
    StartInspection,
    UpdateCorrectiveAction
};
use App\Domains\WorkCore\System\Modules\Assurance\Contracts\AssuranceRepositoryContract;
use App\Domains\WorkCore\System\Modules\Assurance\Repositories\EloquentAssuranceRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition, ReadModelRegistry};
use Illuminate\Support\ServiceProvider;

final class WorkAssuranceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AssuranceRepositoryContract::class, EloquentAssuranceRepository::class);

        $actions = $this->app->make(BusinessActionRegistry::class);
        $definitions = [
            ['workcore.inspection_template.create', CreateInspectionTemplate::class, 'medium', 'templates'],
            ['workcore.inspection.start', StartInspection::class, 'medium', 'inspect'],
            ['workcore.inspection.result.record', RecordInspectionResult::class, 'medium', 'inspect'],
            ['workcore.inspection.complete', CompleteInspection::class, 'high', 'complete'],
            ['workcore.finding.create', CreateFinding::class, 'high', 'findings'],
            ['workcore.corrective_action.create', CreateCorrectiveAction::class, 'high', 'corrective_actions'],
            ['workcore.corrective_action.update', UpdateCorrectiveAction::class, 'high', 'corrective_actions'],
            ['workcore.risk.record', RecordRisk::class, 'high', 'risks'],
            ['workcore.incident.record', RecordIncident::class, 'critical', 'incidents'],
        ];
        foreach ($definitions as [$key, $handler, $risk, $permission]) {
            $actions->register(new ActionDefinition(
                key: $key,
                handler: $handler,
                risk: $risk,
                requiresConfirmation: true,
                capability: 'workcore.assurance',
                permission: (string) config("workcore.assurance.permissions.{$permission}"),
                metadata: ['domain' => 'assurance_compliance', 'canonical_owner' => 'WorkCore Compliance + Assurance'],
            ));
        }

        $reads = $this->app->make(ReadModelRegistry::class);
        $view = (string) config('workcore.assurance.permissions.view');
        $reads->register(new ReadModelDefinition('workcore.inspection.search', SearchInspections::class, 'workcore.assurance', permission: $view));
        $reads->register(new ReadModelDefinition('workcore.finding.search', SearchFindings::class, 'workcore.assurance', permission: $view));
        $reads->register(new ReadModelDefinition('workcore.risk.search', SearchRisks::class, 'workcore.assurance', permission: $view));
        $reads->register(new ReadModelDefinition('workcore.incident.search', SearchIncidents::class, 'workcore.assurance', permission: $view));
        $reads->register(new ReadModelDefinition('workcore.assurance.summary', GetAssuranceSummary::class, 'workcore.assurance', permission: $view));
    }

    public function boot(): void
    {
        if ((bool) config('workcore.assurance.routes_enabled', false) && is_file(__DIR__ . '/../routes/api.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }
}

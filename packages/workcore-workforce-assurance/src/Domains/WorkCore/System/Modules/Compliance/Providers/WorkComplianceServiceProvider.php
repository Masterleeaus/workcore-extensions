<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Compliance\Providers;

use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\ComplianceObligationAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\ComplianceObligationAccessAdapter;
use App\Domains\WorkCore\System\Modules\Compliance\Actions\{ChangeComplianceObligationStatus,ChangeHazardStatus,ChangeSafetyIncidentStatus,ConvertCorrectiveActionToWorkOrder,CreateComplianceObligation,CreateComplianceRequirement,CreateCorrectiveAction,RecordComplianceCertificate,RecordHazard,RecordSafetyIncident,SearchCompliance};
use App\Domains\WorkCore\System\Modules\Compliance\Contracts\ComplianceRepositoryContract;
use App\Domains\WorkCore\System\Modules\Compliance\Repositories\EloquentComplianceRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;

final class WorkComplianceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ComplianceRepositoryContract::class, EloquentComplianceRepository::class);
        $this->app->bind(ComplianceObligationAccessContract::class, ComplianceObligationAccessAdapter::class);
        $actions = $this->app->make(BusinessActionRegistry::class);
        $p = (array) config('workcore.compliance.permissions');
        $actions->register(new ActionDefinition('workcore.compliance.requirement.create', CreateComplianceRequirement::class, 'high', true, 'workcore.compliance', (string) $p['manage_requirements']));
        $actions->register(new ActionDefinition('workcore.compliance.obligation.create', CreateComplianceObligation::class, 'high', true, 'workcore.compliance', (string) $p['manage_obligations']));
        $actions->register(new ActionDefinition('workcore.compliance.obligation.change_status', ChangeComplianceObligationStatus::class, 'high', true, 'workcore.compliance', (string) $p['status']));
        $actions->register(new ActionDefinition('workcore.compliance.certificate.record', RecordComplianceCertificate::class, 'high', true, 'workcore.compliance', (string) $p['certificates']));
        $actions->register(new ActionDefinition('workcore.safety.hazard.record', RecordHazard::class, 'high', true, 'workcore.compliance', (string) $p['hazards']));
        $actions->register(new ActionDefinition('workcore.safety.hazard.change_status', ChangeHazardStatus::class, 'high', true, 'workcore.compliance', (string) $p['hazards']));
        $actions->register(new ActionDefinition('workcore.safety.incident.record', RecordSafetyIncident::class, 'critical', true, 'workcore.compliance', (string) $p['incidents']));
        $actions->register(new ActionDefinition('workcore.safety.incident.change_status', ChangeSafetyIncidentStatus::class, 'critical', true, 'workcore.compliance', (string) $p['incidents']));
        $actions->register(new ActionDefinition('workcore.compliance.corrective_action.create', CreateCorrectiveAction::class, 'high', true, 'workcore.compliance', (string) $p['corrective_actions']));
        $actions->register(new ActionDefinition('workcore.compliance.corrective_action.convert_to_work_order', ConvertCorrectiveActionToWorkOrder::class, 'critical', true, 'workcore.compliance', (string) $p['convert_to_work_order']));
        $this->app->make(ReadModelRegistry::class)->register(new ReadModelDefinition('workcore.compliance.search', SearchCompliance::class, 'workcore.compliance', permission: (string) $p['view']));
    }

    public function boot(): void
    {
        if ((bool) config('workcore.compliance.routes_enabled', false)) $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }
}

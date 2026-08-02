<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Providers;

use App\Domains\WorkCore\System\Actions\{ActionDefinition, BusinessActionRegistry};
use App\Domains\WorkCore\System\Modules\NDIS\Application\Actions\{
    AddNDISCaseNote, LinkNDISParticipantIncident, MatchNDISWorker, PrepareNDISClaimLine,
    RecordNDISSupportDelivery, UpsertNDISGoal, UpsertNDISParticipant,
    UpsertNDISParticipantContact, UpsertNDISPlan, UpsertNDISServiceAgreement
};
use App\Domains\WorkCore\System\Modules\NDIS\Application\ReadModels\{
    GetNDISBudgetPosition, GetNDISParticipantProfile, ListNDISClaimQueue
};
use App\Domains\WorkCore\System\Modules\NDIS\Application\Services\NDISActionSupport;
use App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Actions\{
    AddAccommodationCharge, ChangeAccommodationReservationStatus, CheckInAccommodationStay,
    CheckOutAccommodationStay, CreateAccommodationReservation, UpdateAccommodationHousekeeping,
    UpsertAccommodationRatePlan
};
use App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\ReadModels\{
    GetAccommodationBoard, GetAccommodationFolio, SearchAccommodationAvailability
};
use App\Domains\WorkCore\System\Modules\Premises\Application\Accommodation\Services\{
    AccommodationActionSupport, AccommodationAvailabilityService, AccommodationOccupancyBridge
};
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition, ReadModelRegistry};
use App\Domains\WorkCore\System\Verticals\Actions\{
    ApplyVerticalSetup, PreviewVerticalSetup, UpdateBusinessLine
};
use App\Domains\WorkCore\System\Verticals\Codebooks\{
    CodebookRegistry, CodebookResolver, VerticalValidationRules
};
use App\Domains\WorkCore\System\Verticals\{
    CompanyVerticalManager, CompanyVerticalProfileResolver, TerminologyResolver,
    VerticalProfileCompiler, VerticalRegistry
};
use App\Domains\WorkCore\System\Verticals\Operations\{
    OperationalBlueprintCompiler, OperationalSeedInstaller
};
use App\Domains\WorkCore\System\Verticals\Operations\Actions\{
    AssignWorkerBusinessLine, IssueWorkOrderCertificate, UpsertTradeLicence
};
use App\Domains\WorkCore\System\Verticals\Operations\ReadModels\ListTradeCompliance;
use App\Domains\WorkCore\System\Verticals\ReadModels\{
    GetCompanyVerticalProfile, ListVerticalCatalogue
};
use App\Domains\WorkCore\System\Verticals\Setup\{
    SetupAnswerValidator, VerticalSetupApplicationService, VerticalSetupPlanCompiler,
    VerticalSetupQuestionBank
};
use App\Domains\WorkCore\System\Verticals\TradeCompliance\{
    TradeComplianceActionSupport, TradeCompliancePolicy, TradeComplianceProfileResolver,
    WorkOrderComplianceGate
};
use App\Domains\WorkCore\System\Verticals\TradeCompliance\Actions\{
    AddWorkOrderEvidence, CreateWorkOrderCallback, PrepareWorkOrderCompliance,
    RecordWorkOrderPermit, RecordWorkOrderSafetyControl, RecordWorkOrderTestResult,
    RegisterWorkOrderWarranty, UpsertTradeComplianceProfile
};
use App\Domains\WorkCore\System\Verticals\TradeCompliance\ReadModels\{
    GetWorkOrderTradeCompliance, ListTradeComplianceProfiles
};
use App\Domains\WorkCore\System\Verticals\Workflows\WorkflowResolver;
use Illuminate\Support\ServiceProvider;

final class WorkCoreVerticalOperationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerServices();
        $this->registerActions();
        $this->registerReadModels();
    }

    private function registerServices(): void
    {
        $this->app->singleton(VerticalRegistry::class, static function (): VerticalRegistry {
            $registry = new VerticalRegistry();
            if ((bool) config('workcore.verticals.enabled', true)) {
                $registry->loadDirectory((string) config(
                    'workcore.verticals.manifest_path',
                    dirname(__DIR__, 3) . '/Verticals/Definitions',
                ));
                $registry->validateDependencies();
            }

            return $registry;
        });

        $this->app->singleton(CodebookRegistry::class, static fn (): CodebookRegistry => new CodebookRegistry(
            (array) config('workcore.vertical_codebooks', []),
        ));
        $this->app->singleton(CodebookResolver::class);
        $this->app->scoped(VerticalValidationRules::class);
        $this->app->singleton(WorkflowResolver::class);
        $this->app->singleton(VerticalProfileCompiler::class);
        $this->app->singleton(TerminologyResolver::class);
        $this->app->scoped(CompanyVerticalProfileResolver::class);
        $this->app->scoped(CompanyVerticalManager::class);
        $this->app->singleton(VerticalSetupQuestionBank::class, fn (): VerticalSetupQuestionBank => new VerticalSetupQuestionBank(
            $this->app->make(VerticalRegistry::class),
            (array) config('workcore.vertical_setup', []),
        ));
        $this->app->singleton(SetupAnswerValidator::class);
        $this->app->singleton(OperationalBlueprintCompiler::class, static fn (): OperationalBlueprintCompiler => new OperationalBlueprintCompiler(
            (array) config('workcore.vertical_operations', []),
        ));
        $this->app->scoped(OperationalSeedInstaller::class);
        $this->app->singleton(TradeCompliancePolicy::class, static fn (): TradeCompliancePolicy => new TradeCompliancePolicy(
            array_keys((array) config('workcore.trade_compliance.requirement_types', [])),
        ));
        $this->app->scoped(TradeComplianceProfileResolver::class);
        $this->app->scoped(WorkOrderComplianceGate::class);
        $this->app->scoped(TradeComplianceActionSupport::class);
        $this->app->singleton(VerticalSetupPlanCompiler::class, fn (): VerticalSetupPlanCompiler => new VerticalSetupPlanCompiler(
            $this->app->make(VerticalRegistry::class),
            $this->app->make(VerticalSetupQuestionBank::class),
            $this->app->make(SetupAnswerValidator::class),
            (array) config('workcore.vertical_setup', []),
            $this->app->make(OperationalBlueprintCompiler::class),
        ));
        $this->app->scoped(VerticalSetupApplicationService::class);
        $this->app->scoped(AccommodationActionSupport::class);
        $this->app->scoped(AccommodationAvailabilityService::class);
        $this->app->scoped(AccommodationOccupancyBridge::class);
        $this->app->scoped(NDISActionSupport::class);
    }

    private function registerActions(): void
    {
        $actions = $this->app->make(BusinessActionRegistry::class);
        $verticalManage = (string) config('workcore.verticals.permissions.manage');
        $verticalCompliance = (string) config('workcore.verticals.permissions.compliance');
        $tradeProfiles = (string) config('workcore.trade_compliance.permissions.profiles_manage');
        $tradeJobs = (string) config('workcore.trade_compliance.permissions.jobs_manage');
        $tradeWarranty = (string) config('workcore.trade_compliance.permissions.warranties_manage');

        foreach ([
            new ActionDefinition('workcore.vertical_setup.preview', PreviewVerticalSetup::class, 'low', false, 'workcore.core', $verticalManage),
            new ActionDefinition('workcore.vertical_setup.apply', ApplyVerticalSetup::class, 'high', true, 'workcore.core', $verticalManage),
            new ActionDefinition('workcore.business_line.update', UpdateBusinessLine::class, 'medium', true, 'workcore.core', $verticalManage),
            new ActionDefinition('workcore.worker_business_line.assign', AssignWorkerBusinessLine::class, 'medium', true, 'workcore.workforce', $verticalManage),
            new ActionDefinition('workcore.trade_licence.upsert', UpsertTradeLicence::class, 'medium', true, 'workcore.workforce', $verticalCompliance),
            new ActionDefinition('workcore.work_order_certificate.issue', IssueWorkOrderCertificate::class, 'high', true, 'workcore.operations', $verticalCompliance),
            new ActionDefinition('workcore.trade_compliance.profile.upsert', UpsertTradeComplianceProfile::class, 'high', true, 'workcore.trade_compliance', $tradeProfiles),
            new ActionDefinition('workcore.trade_compliance.prepare', PrepareWorkOrderCompliance::class, 'high', true, 'workcore.trade_compliance', $tradeJobs),
            new ActionDefinition('workcore.trade_compliance.permit.record', RecordWorkOrderPermit::class, 'high', true, 'workcore.trade_compliance', $tradeJobs),
            new ActionDefinition('workcore.trade_compliance.safety_control.record', RecordWorkOrderSafetyControl::class, 'medium', true, 'workcore.trade_compliance', $tradeJobs),
            new ActionDefinition('workcore.trade_compliance.test_result.record', RecordWorkOrderTestResult::class, 'high', true, 'workcore.trade_compliance', $tradeJobs),
            new ActionDefinition('workcore.trade_compliance.evidence.add', AddWorkOrderEvidence::class, 'medium', false, 'workcore.trade_compliance', $tradeJobs),
            new ActionDefinition('workcore.trade_compliance.warranty.register', RegisterWorkOrderWarranty::class, 'high', true, 'workcore.trade_compliance', $tradeWarranty),
            new ActionDefinition('workcore.trade_compliance.callback.create', CreateWorkOrderCallback::class, 'high', true, 'workcore.trade_compliance', $tradeWarranty),
        ] as $definition) {
            $actions->register($definition);
        }

        $accommodation = (array) config('workcore.accommodation.permissions', []);
        foreach ([
            new ActionDefinition('workcore.accommodation.rate_plan.upsert', UpsertAccommodationRatePlan::class, 'medium', true, 'workcore.accommodation', (string) ($accommodation['manage'] ?? '')),
            new ActionDefinition('workcore.accommodation.reservation.create', CreateAccommodationReservation::class, 'medium', true, 'workcore.accommodation', (string) ($accommodation['manage'] ?? '')),
            new ActionDefinition('workcore.accommodation.reservation.status', ChangeAccommodationReservationStatus::class, 'medium', true, 'workcore.accommodation', (string) ($accommodation['manage'] ?? '')),
            new ActionDefinition('workcore.accommodation.stay.check_in', CheckInAccommodationStay::class, 'high', true, 'workcore.accommodation', (string) ($accommodation['front_desk'] ?? '')),
            new ActionDefinition('workcore.accommodation.stay.check_out', CheckOutAccommodationStay::class, 'high', true, 'workcore.accommodation', (string) ($accommodation['front_desk'] ?? '')),
            new ActionDefinition('workcore.accommodation.housekeeping.update', UpdateAccommodationHousekeeping::class, 'medium', false, 'workcore.accommodation', (string) ($accommodation['housekeeping'] ?? '')),
            new ActionDefinition('workcore.accommodation.charge.add', AddAccommodationCharge::class, 'medium', true, 'workcore.accommodation', (string) ($accommodation['charges'] ?? '')),
        ] as $definition) {
            $actions->register($definition);
        }

        $ndis = (array) config('workcore.ndis.permissions', []);
        foreach ([
            new ActionDefinition('workcore.ndis.participant.upsert', UpsertNDISParticipant::class, 'high', true, 'workcore.ndis', (string) ($ndis['participants'] ?? '')),
            new ActionDefinition('workcore.ndis.contact.upsert', UpsertNDISParticipantContact::class, 'high', true, 'workcore.ndis', (string) ($ndis['participants'] ?? '')),
            new ActionDefinition('workcore.ndis.plan.upsert', UpsertNDISPlan::class, 'high', true, 'workcore.ndis', (string) ($ndis['plans'] ?? '')),
            new ActionDefinition('workcore.ndis.goal.upsert', UpsertNDISGoal::class, 'medium', true, 'workcore.ndis', (string) ($ndis['plans'] ?? '')),
            new ActionDefinition('workcore.ndis.agreement.upsert', UpsertNDISServiceAgreement::class, 'high', true, 'workcore.ndis', (string) ($ndis['agreements'] ?? '')),
            new ActionDefinition('workcore.ndis.delivery.record', RecordNDISSupportDelivery::class, 'high', true, 'workcore.ndis', (string) ($ndis['delivery'] ?? '')),
            new ActionDefinition('workcore.ndis.case_note.add', AddNDISCaseNote::class, 'high', true, 'workcore.ndis', (string) ($ndis['notes'] ?? '')),
            new ActionDefinition('workcore.ndis.incident.link', LinkNDISParticipantIncident::class, 'high', true, 'workcore.ndis', (string) ($ndis['incidents'] ?? '')),
            new ActionDefinition('workcore.ndis.worker.match', MatchNDISWorker::class, 'high', true, 'workcore.ndis', (string) ($ndis['worker_matching'] ?? '')),
            new ActionDefinition('workcore.ndis.claim.prepare', PrepareNDISClaimLine::class, 'high', true, 'workcore.ndis', (string) ($ndis['claims'] ?? '')),
        ] as $definition) {
            $actions->register($definition);
        }
    }

    private function registerReadModels(): void
    {
        $reads = $this->app->make(ReadModelRegistry::class);
        $verticalView = (string) config('workcore.verticals.permissions.view', config('workcore.verticals.permissions.manage'));
        $tradeView = (string) config('workcore.trade_compliance.permissions.view');
        $accommodationView = (string) config('workcore.accommodation.permissions.view');
        $ndisView = (string) config('workcore.ndis.permissions.view');

        foreach ([
            new ReadModelDefinition('workcore.vertical.catalogue', ListVerticalCatalogue::class, 'workcore.core', permission: $verticalView),
            new ReadModelDefinition('workcore.vertical.profile', GetCompanyVerticalProfile::class, 'workcore.core', permission: $verticalView),
            new ReadModelDefinition('workcore.trade_compliance.list', ListTradeCompliance::class, 'workcore.workforce', permission: $tradeView),
            new ReadModelDefinition('workcore.trade_compliance.work_order', GetWorkOrderTradeCompliance::class, 'workcore.trade_compliance', permission: $tradeView),
            new ReadModelDefinition('workcore.trade_compliance.profiles', ListTradeComplianceProfiles::class, 'workcore.trade_compliance', permission: $tradeView),
            new ReadModelDefinition('workcore.accommodation.availability', SearchAccommodationAvailability::class, 'workcore.accommodation', permission: $accommodationView),
            new ReadModelDefinition('workcore.accommodation.board', GetAccommodationBoard::class, 'workcore.accommodation', permission: $accommodationView),
            new ReadModelDefinition('workcore.accommodation.folio', GetAccommodationFolio::class, 'workcore.accommodation', permission: $accommodationView),
            new ReadModelDefinition('workcore.ndis.participant.profile', GetNDISParticipantProfile::class, 'workcore.ndis', permission: $ndisView),
            new ReadModelDefinition('workcore.ndis.budget.position', GetNDISBudgetPosition::class, 'workcore.ndis', permission: $ndisView),
            new ReadModelDefinition('workcore.ndis.claim.queue', ListNDISClaimQueue::class, 'workcore.ndis', permission: $ndisView),
        ] as $definition) {
            $reads->register($definition);
        }
    }
}

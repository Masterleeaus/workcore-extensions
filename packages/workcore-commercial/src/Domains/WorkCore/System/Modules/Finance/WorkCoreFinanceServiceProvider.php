<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance;

use App\Domains\WorkCore\System\Modules\Finance\Application\ActionRegistry;
use App\Domains\WorkCore\System\Modules\Finance\Automation\Collections\CollectionSequence;
use App\Domains\WorkCore\System\Modules\Finance\Automation\Invoicing\InvoiceEligibilityEvaluator;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ageing\PayablesAgeingCalculator;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ageing\ReceivablesAgeingCalculator;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceLifecycle;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\QuoteLifecycle;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Forecasting\CashflowForecaster;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\PostingTemplates\InvoiceIssuedPostingTemplate;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\PostingTemplates\PaymentReceivedPostingTemplate;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\PostingTemplates\PaymentConfirmedPostingTemplate;
use App\Domains\WorkCore\System\Modules\Finance\Automation\Collections\CollectionSuppressionEvaluator;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentQrPayloadBuilder;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Profitability\JobProfitabilityCalculator;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation\BankDepositMatcher;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Tax\GstCalculator;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Tax\GstReportCalculator;
use App\Domains\WorkCore\System\Modules\Finance\Application\Actions\GetTitanMoneyHealth;
use App\Domains\WorkCore\System\Modules\Finance\Application\Actions\CloseReconciliation;
use App\Domains\WorkCore\System\Modules\Finance\Application\Actions\ImportBankStatement;
use App\Domains\WorkCore\System\Modules\Finance\Automation\Runtime\PulseFinancialActionWorker;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInputFactory;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\BankImportRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\BankTransactionWriter;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ExternalEventInboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinancialActionInvoker;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\OutboxRelayQueue;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PayPalWebhookVerifier;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PayPalConnectionResolver;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentEvidenceIngestor;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ReconciliationRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ScheduledActionQueue;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\SignalPublisher;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TitanTalkTransport;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed\FailClosedPayPalWebhookVerifier;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed\FailClosedPayPalConnectionResolver;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed\FailClosedSignalPublisher;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed\FailClosedTitanTalkTransport;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Integrations\ActionPaymentEvidenceIngestor;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseBankImportRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseBankTransactionWriter;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseExternalEventInboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseOutboxRelayQueue;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseReconciliationRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseScheduledActionQueue;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Runtime\LaravelFinancialActionInvoker;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Runtime\TitanMoneyActionInputFactory;
use App\Domains\WorkCore\System\Modules\Finance\Integrations\Banking\AustralianBankCsvParser;
use App\Domains\WorkCore\System\Modules\Finance\Integrations\Banking\BankStatementImportService;
use App\Domains\WorkCore\System\Modules\Finance\Integrations\PayPal\PayPalSettlementWebhookHandler;
use App\Domains\WorkCore\System\Modules\Finance\Integrations\Runtime\TitanMoneyOutboxRelay;
use App\Domains\WorkCore\System\Modules\Finance\Console\RelayTitanMoneyOutboxCommand;
use App\Domains\WorkCore\System\Modules\Finance\Console\RunPulseFinancialActionsCommand;
use App\Domains\WorkCore\System\Modules\Finance\Application\Actions\CreateInvoiceFromCompletedJob;
use App\Domains\WorkCore\System\Modules\Finance\Application\Actions\ExecuteCollectionFollowup;
use App\Domains\WorkCore\System\Modules\Finance\Application\Actions\IngestAndMatchPaymentEvidence;
use App\Domains\WorkCore\System\Modules\Finance\Application\Actions\ConfirmPaymentMatch;
use App\Domains\WorkCore\System\Modules\Finance\Application\Services\InvoiceCollectionScheduler;
use App\Domains\WorkCore\System\Modules\Finance\Application\Services\InvoiceIssuer;
use App\Domains\WorkCore\System\Modules\Finance\Application\Services\PaymentRequestService;
use App\Domains\WorkCore\System\Modules\Finance\Application\Services\PaymentConfirmationService;
use App\Domains\WorkCore\System\Modules\Finance\Application\Services\WorkCoreJobInvoiceAssembler;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionDefinition;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\ApprovalMode;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\RiskLevel;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\SurfaceType;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\FailClosedCompanyMembershipAuthorizer;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\FailClosedPermissionAuthorizer;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\FinanceAccessGate;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\CompanyContextResolver;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ArtifactStore;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AuditRecorder;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AutomationPolicyRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AutomationScheduler;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\CollectionAccountStateRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\CollectionMessageGateway;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionAttemptRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentEvidenceRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ReceivableRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentMatchRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ReceiptRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinancialExceptionRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\DocumentNumberGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\DocumentSnapshotRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdempotencyStore;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\InvoiceRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\JournalRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\OutboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentInstructionDeliveryGateway;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentMethodConfigurationRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentRequestRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\QrArtifactRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\QrCodeRenderer;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TransactionManager;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\WorkCoreCommercialSource;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\CompanyMembershipAuthorizer;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PermissionAuthorizer;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed\FailClosedArtifactStore;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed\FailClosedPaymentMethodConfigurationRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed\FailClosedWorkCoreCommercialSource;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Identifiers\RandomUuidGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Payments\BaconQrCodeRenderer;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Payments\BankTransferPaymentAdapter;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Payments\CashPaymentAdapter;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Payments\OutboxPaymentInstructionDeliveryGateway;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Payments\PayIdPaymentAdapter;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Payments\PayPalPaymentAdapter;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Payments\PaymentAdapterRegistry;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseAuditRecorder;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseAutomationPolicyRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseAutomationScheduler;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseDocumentNumberGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseDocumentSnapshotRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseIdempotencyStore;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseInvoiceRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseJournalRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseOutboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabasePaymentRequestRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseQrArtifactRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseCollectionAccountStateRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseCollectionMessageGateway;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseActionAttemptRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabasePaymentEvidenceRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseReceivableRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabasePaymentMatchRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabasePaymentRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseReceiptRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\DatabaseFinancialExceptionRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Time\SystemClock;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Transactions\LaravelTransactionManager;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\DecimalMoneyParser;
use App\Domains\WorkCore\System\Modules\Finance\Numbering\SequenceFormatter;
use App\Domains\WorkCore\System\Modules\Finance\Support\DependencyDiagnostics;
use App\Domains\WorkCore\System\Modules\Finance\Support\MoneyDomainCatalogue;
use App\Domains\WorkCore\System\Modules\Finance\Support\TitanMoneyHealth;
use App\Domains\WorkCore\System\Modules\Finance\Tenancy\FailClosedCompanyContextResolver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\CustomerRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\JobRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\WorkCoreCustomerRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence\WorkCoreJobRepository;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Integration\WorkCoreSignalPublisher;
use App\Domains\WorkCore\System\Outbox\OutboxPublisherContract;

final class WorkCoreFinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/titan-money.php', 'titan-money');
        $this->mergeConfigFrom(__DIR__ . '/config/automation.php', 'titan-money-automation');

        /** @var list<string> $defaultPermissions */
        $defaultPermissions = require __DIR__ . '/config/permissions.php';
        /** @var list<string> $configuredPermissions */
        $configuredPermissions = config('workcore.finance.permissions', []);
        config([
            'titan-money.permissions' => array_values(array_unique(array_merge(
                $defaultPermissions,
                $configuredPermissions,
            ))),
        ]);

        $this->app->singleton(ActionRegistry::class);
        $this->app->singleton(MoneyDomainCatalogue::class);
        $this->app->singleton(SequenceFormatter::class);
        $this->app->singleton(GstCalculator::class);
        $this->app->singleton(GstReportCalculator::class);
        $this->app->singleton(QuoteLifecycle::class);
        $this->app->singleton(InvoiceLifecycle::class);
        $this->app->singleton(InvoiceIssuedPostingTemplate::class);
        $this->app->singleton(PaymentReceivedPostingTemplate::class);
        $this->app->singleton(PaymentConfirmedPostingTemplate::class);
        $this->app->singleton(CollectionSuppressionEvaluator::class);
        $this->app->singleton(ReceivablesAgeingCalculator::class);
        $this->app->singleton(PayablesAgeingCalculator::class);
        $this->app->singleton(BankDepositMatcher::class);
        $this->app->singleton(CashflowForecaster::class);
        $this->app->singleton(JobProfitabilityCalculator::class);
        $this->app->singleton(InvoiceEligibilityEvaluator::class);
        $this->app->singleton(PaymentQrPayloadBuilder::class);
        $this->app->singleton(CollectionSequence::class, static fn (): CollectionSequence => CollectionSequence::australianDefault());

        $this->app->singleton(DecimalMoneyParser::class, static function (): DecimalMoneyParser {
            /** @var array<string, array{minor_units?:int}> $configured */
            $configured = config('workcore.finance.currencies', []);
            $minorUnits = [];
            foreach ($configured as $currency => $definition) {
                if (isset($definition['minor_units']) && is_int($definition['minor_units'])) {
                    $minorUnits[(string) $currency] = $definition['minor_units'];
                }
            }

            return new DecimalMoneyParser($minorUnits);
        });

        $this->registerWorkCoreRepositories();
        $this->registerFailClosedSecurityDefaults();
        $this->registerRuntimeBindings();

        $this->app->singleton(FinanceAccessGate::class, static function ($app): FinanceAccessGate {
            return new FinanceAccessGate(
                $app->make(CompanyMembershipAuthorizer::class),
                $app->make(PermissionAuthorizer::class),
            );
        });

        $this->app->singleton(DependencyDiagnostics::class, static function (): DependencyDiagnostics {
            /** @var array<string, class-string> $required */
            $required = config('workcore.finance.dependencies.required', []);
            /** @var array<string, class-string> $optional */
            $optional = config('workcore.finance.dependencies.optional', []);

            return new DependencyDiagnostics($required, $optional);
        });

        $this->app->singleton(TitanMoneyHealth::class, static function ($app): TitanMoneyHealth {
            return new TitanMoneyHealth(
                config('workcore.finance', []),
                $app->make(DependencyDiagnostics::class),
                $app->make(ActionRegistry::class),
                $app->make(MoneyDomainCatalogue::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', 'titan-money');
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'titan-money');
        if ((bool) config('workcore.finance.routes_enabled', false)) {
            $this->registerRoutes();
        }
        $this->registerBuiltInActions();
        $this->registerPublishables();
        if ($this->app->runningInConsole()) {
            $this->commands([RunPulseFinancialActionsCommand::class, RelayTitanMoneyOutboxCommand::class]);
        }
    }


    private function registerWorkCoreRepositories(): void
    {
        if (! $this->app->bound(CustomerRepository::class)) {
            $this->app->singleton(CustomerRepository::class, WorkCoreCustomerRepository::class);
        }

        if (! $this->app->bound(JobRepository::class)) {
            $this->app->singleton(JobRepository::class, WorkCoreJobRepository::class);
        }

        if (! $this->app->bound(SignalPublisher::class)) {
            $this->app->singleton(SignalPublisher::class, static function ($app): SignalPublisher {
                return new WorkCoreSignalPublisher($app->make(OutboxPublisherContract::class));
            });
        }
    }

    private function registerFailClosedSecurityDefaults(): void
    {
        if (! $this->app->bound(CompanyContextResolver::class)) {
            $this->app->singleton(CompanyContextResolver::class, FailClosedCompanyContextResolver::class);
        }

        if (! $this->app->bound(CompanyMembershipAuthorizer::class)) {
            $this->app->singleton(CompanyMembershipAuthorizer::class, FailClosedCompanyMembershipAuthorizer::class);
        }

        if (! $this->app->bound(PermissionAuthorizer::class)) {
            $this->app->singleton(PermissionAuthorizer::class, FailClosedPermissionAuthorizer::class);
        }
    }

    private function registerRuntimeBindings(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(IdentifierGenerator::class, RandomUuidGenerator::class);
        $this->app->singleton(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->singleton(DocumentNumberGenerator::class, DatabaseDocumentNumberGenerator::class);
        $this->app->singleton(IdempotencyStore::class, DatabaseIdempotencyStore::class);
        $this->app->singleton(InvoiceRepository::class, DatabaseInvoiceRepository::class);
        $this->app->singleton(PaymentRequestRepository::class, DatabasePaymentRequestRepository::class);
        $this->app->singleton(OutboxRepository::class, DatabaseOutboxRepository::class);
        $this->app->singleton(QrArtifactRepository::class, DatabaseQrArtifactRepository::class);
        $this->app->singleton(DocumentSnapshotRepository::class, DatabaseDocumentSnapshotRepository::class);
        $this->app->singleton(JournalRepository::class, DatabaseJournalRepository::class);
        $this->app->singleton(AutomationScheduler::class, DatabaseAutomationScheduler::class);
        $this->app->singleton(AutomationPolicyRepository::class, DatabaseAutomationPolicyRepository::class);
        $this->app->singleton(AuditRecorder::class, DatabaseAuditRecorder::class);
        $this->app->singleton(QrCodeRenderer::class, BaconQrCodeRenderer::class);
        $this->app->singleton(PaymentInstructionDeliveryGateway::class, OutboxPaymentInstructionDeliveryGateway::class);
        $this->app->singleton(CollectionAccountStateRepository::class, DatabaseCollectionAccountStateRepository::class);
        $this->app->singleton(CollectionMessageGateway::class, DatabaseCollectionMessageGateway::class);
        $this->app->singleton(ActionAttemptRepository::class, DatabaseActionAttemptRepository::class);
        $this->app->singleton(PaymentEvidenceRepository::class, DatabasePaymentEvidenceRepository::class);
        $this->app->singleton(ReceivableRepository::class, DatabaseReceivableRepository::class);
        $this->app->singleton(PaymentMatchRepository::class, DatabasePaymentMatchRepository::class);
        $this->app->singleton(PaymentRepository::class, DatabasePaymentRepository::class);
        $this->app->singleton(ReceiptRepository::class, DatabaseReceiptRepository::class);
        $this->app->singleton(FinancialExceptionRepository::class, DatabaseFinancialExceptionRepository::class);
        $this->app->singleton(ScheduledActionQueue::class, DatabaseScheduledActionQueue::class);
        $this->app->singleton(OutboxRelayQueue::class, DatabaseOutboxRelayQueue::class);
        $this->app->singleton(ExternalEventInboxRepository::class, DatabaseExternalEventInboxRepository::class);
        $this->app->singleton(BankImportRepository::class, DatabaseBankImportRepository::class);
        $this->app->singleton(BankTransactionWriter::class, DatabaseBankTransactionWriter::class);
        $this->app->singleton(ReconciliationRepository::class, DatabaseReconciliationRepository::class);
        $this->app->singleton(PaymentEvidenceIngestor::class, ActionPaymentEvidenceIngestor::class);
        $this->app->singleton(ActionInputFactory::class, TitanMoneyActionInputFactory::class);
        $this->app->singleton(FinancialActionInvoker::class, LaravelFinancialActionInvoker::class);

        if (! $this->app->bound(SignalPublisher::class)) {
            $this->app->singleton(SignalPublisher::class, FailClosedSignalPublisher::class);
        }
        if (! $this->app->bound(TitanTalkTransport::class)) {
            $this->app->singleton(TitanTalkTransport::class, FailClosedTitanTalkTransport::class);
        }
        if (! $this->app->bound(PayPalWebhookVerifier::class)) {
            $this->app->singleton(PayPalWebhookVerifier::class, FailClosedPayPalWebhookVerifier::class);
        }
        if (! $this->app->bound(PayPalConnectionResolver::class)) {
            $this->app->singleton(PayPalConnectionResolver::class, FailClosedPayPalConnectionResolver::class);
        }

        if (! $this->app->bound(WorkCoreCommercialSource::class)) {
            $this->app->singleton(WorkCoreCommercialSource::class, FailClosedWorkCoreCommercialSource::class);
        }
        if (! $this->app->bound(PaymentMethodConfigurationRepository::class)) {
            $this->app->singleton(PaymentMethodConfigurationRepository::class, FailClosedPaymentMethodConfigurationRepository::class);
        }
        if (! $this->app->bound(ArtifactStore::class)) {
            $this->app->singleton(ArtifactStore::class, FailClosedArtifactStore::class);
        }

        $this->app->singleton(PaymentAdapterRegistry::class, static function (): PaymentAdapterRegistry {
            return new PaymentAdapterRegistry([
                new CashPaymentAdapter(),
                new PayIdPaymentAdapter(),
                new BankTransferPaymentAdapter(),
                new PayPalPaymentAdapter(),
            ]);
        });
        $this->app->singleton(WorkCoreJobInvoiceAssembler::class);
        $this->app->singleton(InvoiceCollectionScheduler::class);
        $this->app->singleton(InvoiceIssuer::class);
        $this->app->singleton(PaymentRequestService::class);
        $this->app->singleton(PaymentConfirmationService::class);
        $this->app->singleton(ExecuteCollectionFollowup::class);
        $this->app->singleton(ConfirmPaymentMatch::class);
        $this->app->singleton(IngestAndMatchPaymentEvidence::class, static function ($app): IngestAndMatchPaymentEvidence {
            return new IngestAndMatchPaymentEvidence(
                $app->make(FinanceAccessGate::class),
                $app->make(PaymentEvidenceRepository::class),
                $app->make(ReceivableRepository::class),
                $app->make(PaymentMatchRepository::class),
                $app->make(BankDepositMatcher::class),
                $app->make(PaymentConfirmationService::class),
                $app->make(FinancialExceptionRepository::class),
                $app->make(AuditRecorder::class),
                $app->make(OutboxRepository::class),
                $app->make(IdempotencyStore::class),
                $app->make(Clock::class),
                $app->make(IdentifierGenerator::class),
                $app->make(TransactionManager::class),
                (int) config('workcore.finance.autonomy.automatic_match_threshold', 95),
            );
        });
        $this->app->singleton(CreateInvoiceFromCompletedJob::class);
        $this->app->singleton(AustralianBankCsvParser::class);
        $this->app->singleton(BankStatementImportService::class);
        $this->app->singleton(PayPalSettlementWebhookHandler::class);
        $this->app->singleton(PulseFinancialActionWorker::class);
        $this->app->singleton(TitanMoneyOutboxRelay::class);
        $this->app->singleton(CloseReconciliation::class);
        $this->app->singleton(ImportBankStatement::class);
    }

    private function registerRoutes(): void
    {
        Route::middleware(['web', 'auth'])
            ->prefix('dashboard/user/titan-money')
            ->name('dashboard.user.titan-money.')
            ->group(__DIR__ . '/Http/Routes/web.php');

        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/titan-money')
            ->name('api.titan-money.')
            ->group(__DIR__ . '/Http/Routes/api.php');

        Route::middleware(['api', 'throttle:60,1'])
            ->prefix('api/titan-money/webhooks')
            ->name('api.titan-money.webhooks.')
            ->group(__DIR__ . '/Http/Routes/webhooks.php');
    }

    private function registerBuiltInActions(): void
    {
        $this->app->make(ActionRegistry::class)->register(new ActionDefinition(
            key: 'money.health',
            actionClass: GetTitanMoneyHealth::class,
            description: 'Read Titan Money extension, dependency, feature, security-adapter, and interface health.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
            ],
            riskLevel: RiskLevel::Low,
            approvalMode: ApprovalMode::None,
            surfaces: [
                SurfaceType::Workspace,
                SurfaceType::MobileCard,
                SurfaceType::TabletPanel,
                SurfaceType::Headless,
            ],
        ));

        $this->app->make(ActionRegistry::class)->register(new ActionDefinition(
            key: 'money.create_invoice_from_job',
            actionClass: CreateInvoiceFromCompletedJob::class,
            description: 'Create a Titan Money invoice from a completed WorkCore job, apply autonomy policy, post the ledger, generate payment QR instructions, and schedule collection follow-up.',
            inputSchema: [
                'type' => 'object',
                'required' => ['job_id'],
                'properties' => [
                    'job_id' => ['type' => 'string', 'minLength' => 1],
                    'issue_when_policy_allows' => ['type' => 'boolean'],
                    'send_payment_instructions' => ['type' => 'boolean'],
                    'preferred_payment_method' => ['enum' => ['cash', 'payid', 'bank_transfer', 'paypal_card']],
                    'delivery_channels' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'additionalProperties' => false,
            ],
            riskLevel: RiskLevel::Medium,
            approvalMode: ApprovalMode::UserConfirmation,
            surfaces: [
                SurfaceType::Workspace,
                SurfaceType::MobileCard,
                SurfaceType::TabletPanel,
                SurfaceType::Headless,
            ],
        ));

        $this->app->make(ActionRegistry::class)->register(new ActionDefinition(
            key: 'money.ingest_payment_evidence',
            actionClass: IngestAndMatchPaymentEvidence::class,
            description: 'Ingest payment evidence, rank receivable matches, auto-confirm policy-safe matches, allocate funds, issue a receipt, and stop completed collection workflows.',
            inputSchema: [
                'type' => 'object',
                'required' => ['source_type','amount_minor','currency_code','raw_payload_hash','observed_at'],
                'properties' => [
                    'source_type' => ['type'=>'string'],
                    'payment_method' => ['enum'=>['cash','payid','bank_transfer','paypal_card']],
                    'amount_minor' => ['type'=>'integer','minimum'=>1],
                    'currency_code' => ['type'=>'string','minLength'=>3,'maxLength'=>3],
                    'reference_text' => ['type'=>'string'],
                    'payer_hint' => ['type'=>['string','null']],
                    'raw_payload_hash' => ['type'=>'string','pattern'=>'^[A-Fa-f0-9]{64}$'],
                    'observed_at' => ['type'=>'string','format'=>'date-time'],
                    'source_device_id' => ['type'=>['string','null']],
                ],
                'additionalProperties' => false,
            ],
            riskLevel: RiskLevel::Medium,
            approvalMode: ApprovalMode::None,
            surfaces: [SurfaceType::Workspace,SurfaceType::MobileCard,SurfaceType::TabletPanel,SurfaceType::Headless],
        ));

        $this->app->make(ActionRegistry::class)->register(new ActionDefinition(
            key: 'money.confirm_payment_match',
            actionClass: ConfirmPaymentMatch::class,
            description: 'Confirm a selected payment-evidence match and apply it through the canonical payment, allocation, ledger, receipt, and reminder workflow.',
            inputSchema: [
                'type'=>'object',
                'required'=>['evidence_id','invoice_id'],
                'properties'=>[
                    'evidence_id'=>['type'=>'string','minLength'=>1],
                    'invoice_id'=>['type'=>'string','minLength'=>1],
                ],
                'additionalProperties'=>false,
            ],
            riskLevel: RiskLevel::High,
            approvalMode: ApprovalMode::UserConfirmation,
            surfaces: [SurfaceType::Workspace,SurfaceType::MobileCard,SurfaceType::TabletPanel,SurfaceType::Headless],
        ));

        $this->app->make(ActionRegistry::class)->register(new ActionDefinition(
            key: 'money.collection.execute_followup',
            actionClass: ExecuteCollectionFollowup::class,
            description: 'Evaluate payment, dispute, promise and pause suppressions before queuing a scheduled customer collection message.',
            inputSchema: [
                'type'=>'object',
                'required'=>['invoice_id','step_code','channels','tone'],
                'properties'=>[
                    'invoice_id'=>['type'=>'string'],
                    'step_code'=>['type'=>'string'],
                    'channels'=>['type'=>'array','items'=>['type'=>'string'],'minItems'=>1],
                    'tone'=>['type'=>'string'],
                    'approval_required'=>['type'=>'boolean'],
                ],
                'additionalProperties'=>false,
            ],
            riskLevel: RiskLevel::Medium,
            approvalMode: ApprovalMode::UserConfirmation,
            surfaces: [SurfaceType::Workspace,SurfaceType::MobileCard,SurfaceType::Headless],
        ));

        $this->app->make(ActionRegistry::class)->register(new ActionDefinition(
            key: 'money.bank_statement.import',
            actionClass: ImportBankStatement::class,
            description: 'Import an Australian bank statement into canonical company-scoped bank transactions with duplicate prevention and row-level errors.',
            inputSchema: [
                'type'=>'object',
                'required'=>['bank_account_id','currency_code','csv','filename'],
                'properties'=>[
                    'bank_account_id'=>['type'=>'string','minLength'=>1],
                    'currency_code'=>['type'=>'string','minLength'=>3,'maxLength'=>3],
                    'csv'=>['type'=>'string','minLength'=>1],
                    'filename'=>['type'=>'string'],
                ],
                'additionalProperties'=>false,
            ],
            riskLevel: RiskLevel::Medium,
            approvalMode: ApprovalMode::UserConfirmation,
            surfaces: [SurfaceType::Workspace,SurfaceType::TabletPanel,SurfaceType::Headless],
        ));

        $this->app->make(ActionRegistry::class)->register(new ActionDefinition(
            key: 'money.reconciliation.close',
            actionClass: CloseReconciliation::class,
            description: 'Close a bank reconciliation only when statement and reconciled balances match and no unresolved lines remain.',
            inputSchema: [
                'type'=>'object',
                'required'=>['reconciliation_id'],
                'properties'=>['reconciliation_id'=>['type'=>'string','minLength'=>1]],
                'additionalProperties'=>false,
            ],
            riskLevel: RiskLevel::High,
            approvalMode: ApprovalMode::ExplicitApproval,
            surfaces: [SurfaceType::Workspace,SurfaceType::TabletPanel,SurfaceType::Headless],
        ));
    }

    private function registerPublishables(): void
    {
        $this->publishes([
            dirname(__DIR__) . '/config/titan-money.php' => config_path('titan-money.php'),
            dirname(__DIR__) . '/config/permissions.php' => config_path('titan-money-permissions.php'),
            dirname(__DIR__) . '/config/automation.php' => config_path('titan-money-automation.php'),
        ], 'titan-money-config');

        $this->publishes([
            dirname(__DIR__) . '/resources/schemas' => resource_path('schemas/titan-money'),
        ], 'titan-money-schemas');
    }
}

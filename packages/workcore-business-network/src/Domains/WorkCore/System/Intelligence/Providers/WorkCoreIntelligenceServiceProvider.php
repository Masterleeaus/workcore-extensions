<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Providers;

use App\Domains\WorkCore\System\Actions\ActionDefinition;
use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use App\Domains\WorkCore\System\Actions\Contracts\ConfirmationVerifierContract;
use App\Domains\WorkCore\System\Intelligence\Actions\CaptureDomainIntelligenceSnapshot;
use App\Domains\WorkCore\System\Intelligence\Actions\IndexKnowledgeDocument;
use App\Domains\WorkCore\System\Intelligence\Actions\ProposeIntelligenceRecommendation;
use App\Domains\WorkCore\System\Intelligence\Actions\PurgeExpiredKnowledge;
use App\Domains\WorkCore\System\Intelligence\Actions\RecordIntelligenceObservation;
use App\Domains\WorkCore\System\Intelligence\Actions\RemoveKnowledgeDocument;
use App\Domains\WorkCore\System\Intelligence\Approvals\BoundConfirmationVerifier;
use App\Domains\WorkCore\System\Intelligence\Approvals\ConfirmationGrantService;
use App\Domains\WorkCore\System\Intelligence\Approvals\ConfirmationGrantSigner;
use App\Domains\WorkCore\System\Intelligence\Approvals\ConfirmationNonceStoreContract;
use App\Domains\WorkCore\System\Intelligence\Approvals\DatabaseConfirmationNonceStore;
use App\Domains\WorkCore\System\Intelligence\Evidence\DatabaseEvidenceStore;
use App\Domains\WorkCore\System\Intelligence\Evidence\EvidenceChain;
use App\Domains\WorkCore\System\Intelligence\Evidence\EvidenceStoreContract;
use App\Domains\WorkCore\System\Intelligence\Domains\Adapters\ComplianceQualityIntelligenceAdapter;
use App\Domains\WorkCore\System\Intelligence\Domains\Adapters\CrmIntelligenceAdapter;
use App\Domains\WorkCore\System\Intelligence\Domains\Adapters\FinanceIntelligenceAdapter;
use App\Domains\WorkCore\System\Intelligence\Domains\Adapters\OperationsIntelligenceAdapter;
use App\Domains\WorkCore\System\Intelligence\Domains\Adapters\PremisesAssetsIntelligenceAdapter;
use App\Domains\WorkCore\System\Intelligence\Domains\Adapters\SupplyIntelligenceAdapter;
use App\Domains\WorkCore\System\Intelligence\Domains\Adapters\WorkforceIntelligenceAdapter;
use App\Domains\WorkCore\System\Intelligence\Domains\Contracts\DomainMetricProviderContract;
use App\Domains\WorkCore\System\Intelligence\Domains\DatabaseDomainMetricProvider;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainActionAuthorizer;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainIntelligenceContextService;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainIntelligenceRegistry;
use App\Domains\WorkCore\System\Intelligence\Knowledge\CharacterChunker;
use App\Domains\WorkCore\System\Intelligence\Knowledge\DatabaseKnowledgeIndex;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeContextBuilder;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeFreshnessPolicy;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeIndexContract;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeIndexStatusContract;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeIngestionService;
use App\Domains\WorkCore\System\Intelligence\Knowledge\LexicalRelevanceScorer;
use App\Domains\WorkCore\System\Intelligence\Knowledge\NullKnowledgeIndex;
use App\Domains\WorkCore\System\Intelligence\Knowledge\ReciprocalRankFusion;
use App\Domains\WorkCore\System\Intelligence\Knowledge\VectorValidator;
use App\Domains\WorkCore\System\Intelligence\Knowledge\Retention\KnowledgeRetentionPolicy;
use App\Domains\WorkCore\System\Intelligence\Learning\CapabilityMatchRanker;
use App\Domains\WorkCore\System\Intelligence\Observations\DatabaseObservationRepository;
use App\Domains\WorkCore\System\Intelligence\Observations\ObservationRepositoryContract;
use App\Domains\WorkCore\System\Intelligence\Proposals\BalancedJsonExtractor;
use App\Domains\WorkCore\System\Intelligence\Proposals\ProposalIntake;
use App\Domains\WorkCore\System\Intelligence\Proposals\StrictSchemaValidator;
use App\Domains\WorkCore\System\Intelligence\ReadModels\GetDomainIntelligenceContext;
use App\Domains\WorkCore\System\Intelligence\ReadModels\GetDomainIntelligencePortfolio;
use App\Domains\WorkCore\System\Intelligence\ReadModels\GetIntelligenceStatus;
use App\Domains\WorkCore\System\Intelligence\ReadModels\GetKnowledgeDocumentStatus;
use App\Domains\WorkCore\System\Intelligence\ReadModels\ListDomainIntelligenceAdapters;
use App\Domains\WorkCore\System\Intelligence\ReadModels\ListIntelligenceObservations;
use App\Domains\WorkCore\System\Intelligence\ReadModels\ListIntelligenceRecommendations;
use App\Domains\WorkCore\System\Intelligence\ReadModels\ScanIntelligenceInput;
use App\Domains\WorkCore\System\Intelligence\ReadModels\SearchKnowledge;
use App\Domains\WorkCore\System\Intelligence\Recommendations\DatabaseRecommendationRepository;
use App\Domains\WorkCore\System\Intelligence\Recommendations\RecommendationRepositoryContract;
use App\Domains\WorkCore\System\Intelligence\Safety\AustralianIdentifierDetector;
use App\Domains\WorkCore\System\Intelligence\Safety\ContentSafetyScanner;
use App\Domains\WorkCore\System\Intelligence\Safety\SecretScanner;
use App\Domains\WorkCore\System\Intelligence\Telemetry\IntelligenceTraceCollector;
use App\Domains\WorkCore\System\ReadModels\ReadModelDefinition;
use App\Domains\WorkCore\System\ReadModels\ReadModelRegistry;
use Illuminate\Support\ServiceProvider;

final class WorkCoreIntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SecretScanner::class);
        $this->app->singleton(AustralianIdentifierDetector::class);
        $this->app->singleton(ContentSafetyScanner::class, static fn ($app): ContentSafetyScanner => new ContentSafetyScanner(
            $app->make(SecretScanner::class),
            $app->make(AustralianIdentifierDetector::class),
            (bool) config('workcore.intelligence.safety.redact_emails', true),
        ));
        $this->app->singleton(BalancedJsonExtractor::class);
        $this->app->singleton(StrictSchemaValidator::class, static fn (): StrictSchemaValidator => new StrictSchemaValidator(
            (int) config('workcore.intelligence.proposals.max_depth', 20),
            (int) config('workcore.intelligence.proposals.max_array_items', 100),
            (int) config('workcore.intelligence.proposals.max_string_length', 10000),
        ));
        $this->app->singleton(ProposalIntake::class, static fn ($app): ProposalIntake => new ProposalIntake(
            $app->make(BalancedJsonExtractor::class),
            $app->make(StrictSchemaValidator::class),
            (int) config('workcore.intelligence.proposals.max_bytes', 100000),
        ));
        $this->app->singleton(CharacterChunker::class, static fn (): CharacterChunker => new CharacterChunker(
            (int) config('workcore.intelligence.knowledge.chunk_size', 1200),
            (int) config('workcore.intelligence.knowledge.chunk_overlap', 150),
        ));
        $this->app->singleton(VectorValidator::class, static fn (): VectorValidator => new VectorValidator(
            (int) config('workcore.intelligence.knowledge.embedding_dimensions', 1536),
            (float) config('workcore.intelligence.knowledge.max_vector_value', 1000000.0),
        ));
        $this->app->singleton(ReciprocalRankFusion::class, static fn (): ReciprocalRankFusion => new ReciprocalRankFusion(
            (int) config('workcore.intelligence.knowledge.rrf_k', 60),
        ));
        $this->app->singleton(KnowledgeFreshnessPolicy::class, static fn (): KnowledgeFreshnessPolicy => new KnowledgeFreshnessPolicy(
            (int) config('workcore.intelligence.knowledge.stale_after_days', 90),
        ));
        $this->app->singleton(KnowledgeRetentionPolicy::class, static fn (): KnowledgeRetentionPolicy => new KnowledgeRetentionPolicy(
            (int) config('workcore.intelligence.knowledge.default_retention_days', 365),
            (int) config('workcore.intelligence.knowledge.deleted_grace_days', 7),
        ));
        $this->app->singleton(LexicalRelevanceScorer::class);
        $this->app->singleton(KnowledgeContextBuilder::class);
        $this->app->singleton(DatabaseKnowledgeIndex::class, static fn ($app): DatabaseKnowledgeIndex => new DatabaseKnowledgeIndex(
            $app->make(\Illuminate\Database\ConnectionInterface::class),
            $app->make(CharacterChunker::class),
            $app->make(KnowledgeFreshnessPolicy::class),
            $app->make(KnowledgeRetentionPolicy::class),
            $app->make(LexicalRelevanceScorer::class),
            (int) config('workcore.intelligence.knowledge.candidate_multiplier', 5),
        ));
        $this->app->bind(KnowledgeIndexContract::class, static function ($app): KnowledgeIndexContract {
            $class = (string) config('workcore.intelligence.knowledge.index', DatabaseKnowledgeIndex::class);
            $index = $app->make($class);
            if (! $index instanceof KnowledgeIndexContract) {
                throw new \RuntimeException('Configured WorkCore knowledge index does not implement KnowledgeIndexContract.');
            }

            return $index;
        });
        $this->app->bind(KnowledgeIndexStatusContract::class, static function ($app): KnowledgeIndexStatusContract {
            $class = (string) config('workcore.intelligence.knowledge.index', DatabaseKnowledgeIndex::class);
            $index = $app->make($class);
            if (! $index instanceof KnowledgeIndexStatusContract) {
                throw new \RuntimeException('Configured WorkCore knowledge index does not expose operational status and retention controls.');
            }

            return $index;
        });
        $this->app->singleton(KnowledgeIngestionService::class, static fn ($app): KnowledgeIngestionService => new KnowledgeIngestionService(
            $app->make(KnowledgeIndexContract::class),
            $app->make(ContentSafetyScanner::class),
            $app->make(SecretScanner::class),
        ));
        $this->app->singleton(DatabaseDomainMetricProvider::class);
        $this->app->singleton(DomainMetricProviderContract::class, DatabaseDomainMetricProvider::class);
        $this->app->singleton(CrmIntelligenceAdapter::class);
        $this->app->singleton(OperationsIntelligenceAdapter::class);
        $this->app->singleton(FinanceIntelligenceAdapter::class);
        $this->app->singleton(WorkforceIntelligenceAdapter::class);
        $this->app->singleton(PremisesAssetsIntelligenceAdapter::class);
        $this->app->singleton(ComplianceQualityIntelligenceAdapter::class);
        $this->app->singleton(SupplyIntelligenceAdapter::class);
        $this->app->singleton(DomainIntelligenceRegistry::class, static function ($app): DomainIntelligenceRegistry {
            $registry = new DomainIntelligenceRegistry();
            $configured = (array) config('workcore.intelligence.domains.adapters', []);
            foreach ($configured as $definition) {
                if (! (bool) ($definition['enabled'] ?? true)) {
                    continue;
                }
                $class = (string) ($definition['class'] ?? '');
                if ($class === '') {
                    continue;
                }
                $registry->register($app->make($class));
            }

            return $registry;
        });
        $this->app->singleton(DomainIntelligenceContextService::class);
        $this->app->singleton(DomainActionAuthorizer::class);
        $this->app->singleton(CapabilityMatchRanker::class, static fn (): CapabilityMatchRanker => new CapabilityMatchRanker(
            (float) config('workcore.intelligence.learning.reuse_threshold', 0.78),
            (float) config('workcore.intelligence.learning.adapt_threshold', 0.42),
        ));
        $this->app->bind(ObservationRepositoryContract::class, DatabaseObservationRepository::class);
        $this->app->bind(RecommendationRepositoryContract::class, DatabaseRecommendationRepository::class);
        $this->app->bind(EvidenceChain::class, static fn (): EvidenceChain => new EvidenceChain(
            (string) config('workcore.intelligence.evidence.signing_key', (string) config('app.key', '')),
            (string) config('workcore.intelligence.evidence.key_id', 'primary'),
        ));
        $this->app->bind(EvidenceStoreContract::class, DatabaseEvidenceStore::class);
        $this->app->scoped(IntelligenceTraceCollector::class, static fn ($app): IntelligenceTraceCollector => new IntelligenceTraceCollector(
            $app->make(ContentSafetyScanner::class),
            (int) config('workcore.intelligence.telemetry.capacity', 200),
        ));

        $this->app->singleton(ConfirmationGrantSigner::class, static fn (): ConfirmationGrantSigner => new ConfirmationGrantSigner(
            (string) config('workcore.intelligence.approvals.signing_key', (string) config('app.key', '')),
            (string) config('workcore.intelligence.approvals.key_id', 'primary'),
        ));
        $this->app->bind(ConfirmationNonceStoreContract::class, DatabaseConfirmationNonceStore::class);
        $this->app->singleton(ConfirmationGrantService::class, static fn ($app): ConfirmationGrantService => new ConfirmationGrantService(
            $app->make(ConfirmationGrantSigner::class),
            $app->make(ConfirmationNonceStoreContract::class),
            (int) config('workcore.intelligence.approvals.ttl_seconds', 300),
        ));
        if ((bool) config('workcore.intelligence.approvals.enabled', true)) {
            $this->app->bind(ConfirmationVerifierContract::class, static fn ($app): BoundConfirmationVerifier => new BoundConfirmationVerifier(
                $app->make(ConfirmationGrantSigner::class),
                $app->make(ConfirmationNonceStoreContract::class),
                array_map('strtolower', (array) config('workcore.intelligence.approvals.ai_sources', [])),
                (bool) config('workcore.intelligence.approvals.enforce_all', false),
                (bool) config('workcore.intelligence.approvals.allow_legacy_human_confirmation', true),
            ));
        }

        $this->registerActions($this->app->make(BusinessActionRegistry::class));
        $this->registerReadModels($this->app->make(ReadModelRegistry::class));
    }

    private function registerActions(BusinessActionRegistry $actions): void
    {
        $actions->register(new ActionDefinition(
            key: 'workcore.intelligence.observation.record',
            handler: RecordIntelligenceObservation::class,
            risk: 'low',
            requiresConfirmation: false,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.permissions.observe'),
            metadata: [
                'description' => 'Record a company-scoped intelligence observation with evidence, confidence and explicit data gaps.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['domain', 'type', 'summary', 'confidence'],
                    'additionalProperties' => false,
                    'properties' => [
                        'domain' => ['type' => 'string', 'maxLength' => 100],
                        'type' => ['type' => 'string', 'maxLength' => 100],
                        'summary' => ['type' => 'string', 'maxLength' => 4000],
                        'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                        'evidence' => ['type' => 'array', 'maxItems' => 50],
                        'data_gaps' => ['type' => 'array', 'maxItems' => 25],
                    ],
                ],
            ],
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.intelligence.recommendation.propose',
            handler: ProposeIntelligenceRecommendation::class,
            risk: 'low',
            requiresConfirmation: false,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.permissions.recommend'),
            metadata: [
                'description' => 'Create a review-only recommendation linked to evidence or an observation; this action never executes the recommendation.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['summary', 'proposed_action', 'confidence', 'risk'],
                    'additionalProperties' => false,
                    'properties' => [
                        'observation_public_id' => ['type' => ['string', 'null']],
                        'summary' => ['type' => 'string', 'maxLength' => 4000],
                        'proposed_action' => ['type' => 'string', 'maxLength' => 255],
                        'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                        'risk' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                        'success_metrics' => ['type' => 'array', 'maxItems' => 20],
                    ],
                ],
            ],
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.intelligence.knowledge.index',
            handler: IndexKnowledgeDocument::class,
            risk: 'low',
            requiresConfirmation: false,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.knowledge.permissions.index'),
            metadata: [
                'description' => 'Index or idempotently re-index a company-scoped knowledge source for governed retrieval.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['title', 'content', 'source_type', 'source_public_id'],
                    'additionalProperties' => false,
                    'properties' => [
                        'document_id' => ['type' => 'string', 'maxLength' => 64],
                        'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                        'content' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2000000],
                        'source_type' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_.:-]{0,79}$'],
                        'source_public_id' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 191],
                        'visibility' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_.:-]{1,79}$'],
                        'required_permission' => ['type' => ['string', 'null'], 'maxLength' => 160],
                        'owner_user_id' => ['type' => ['integer', 'null'], 'minimum' => 1],
                        'source_updated_at' => ['type' => ['string', 'null'], 'maxLength' => 40],
                        'expires_at' => ['type' => ['string', 'null'], 'maxLength' => 40],
                        'metadata' => ['type' => 'object'],
                    ],
                ],
            ],
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.intelligence.knowledge.remove',
            handler: RemoveKnowledgeDocument::class,
            risk: 'medium',
            requiresConfirmation: true,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.knowledge.permissions.remove'),
            metadata: [
                'description' => 'Remove a company-scoped knowledge document and all retrievable chunks.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['document_id'],
                    'additionalProperties' => false,
                    'properties' => ['document_id' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64]],
                ],
            ],
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.intelligence.knowledge.purge_expired',
            handler: PurgeExpiredKnowledge::class,
            risk: 'high',
            requiresConfirmation: true,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.knowledge.permissions.admin'),
            metadata: [
                'description' => 'Permanently purge expired or grace-completed knowledge for the active company.',
                'internal_only' => true,
                'input_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => ['before' => ['type' => ['string', 'null'], 'maxLength' => 40]],
                ],
            ],
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.intelligence.domain.snapshot.capture',
            handler: CaptureDomainIntelligenceSnapshot::class,
            risk: 'low',
            requiresConfirmation: false,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.permissions.observe'),
            metadata: [
                'description' => 'Capture evidence-backed, company-scoped signals from permitted WorkCore domains and create review-only recommendations.',
                'input_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'domains' => ['type' => 'array', 'maxItems' => 7, 'items' => ['type' => 'string']],
                        'max_items' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500],
                        'create_recommendations' => ['type' => 'boolean'],
                    ],
                ],
            ],
        ));

    }

    private function registerReadModels(ReadModelRegistry $reads): void
    {
        $reads->register(new ReadModelDefinition(
            key: 'workcore.intelligence.status',
            handler: GetIntelligenceStatus::class,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.permissions.view'),
            metadata: ['description' => 'Inspect the native WorkCore Intelligence Kernel configuration and safety posture.'],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.intelligence.safety.scan',
            handler: ScanIntelligenceInput::class,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.permissions.view'),
            metadata: [
                'description' => 'Scan prospective model or tool input for secrets and Australian sensitive identifiers before it leaves WorkCore.',
                'input_schema' => ['type' => 'object', 'required' => ['content'], 'properties' => [
                    'content' => ['type' => 'string', 'maxLength' => 100000],
                    'trust_boundary' => ['type' => 'string'],
                ]],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.intelligence.observations.list',
            handler: ListIntelligenceObservations::class,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.permissions.view'),
            metadata: ['description' => 'List company-scoped intelligence observations.', 'input_schema' => ['type' => 'object', 'properties' => ['domain' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]]]],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.intelligence.recommendations.list',
            handler: ListIntelligenceRecommendations::class,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.permissions.view'),
            metadata: ['description' => 'List review-only company-scoped recommendations.', 'input_schema' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string'], 'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]]]],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.intelligence.knowledge.search',
            handler: SearchKnowledge::class,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.permissions.view'),
            metadata: [
                'description' => 'Search company-scoped operational knowledge with SQL-enforced visibility, ownership, permission, expiry and source citations.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['query'],
                    'additionalProperties' => false,
                    'properties' => [
                        'query' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 2000],
                        'filters' => ['type' => 'object'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                        'visibilities' => ['type' => 'array', 'maxItems' => 20, 'items' => ['type' => 'string']],
                    ],
                ],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.intelligence.knowledge.status',
            handler: GetKnowledgeDocumentStatus::class,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.permissions.view'),
            metadata: [
                'description' => 'Inspect indexing, source freshness, retention and chunk status for a company-scoped knowledge document.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['document_id'],
                    'additionalProperties' => false,
                    'properties' => ['document_id' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64]],
                ],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.intelligence.domains.list',
            handler: ListDomainIntelligenceAdapters::class,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.permissions.view'),
            metadata: [
                'description' => 'List native domain intelligence adapters available to the active company actor.',
                'input_schema' => ['type' => 'object', 'additionalProperties' => false],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.intelligence.domain.context',
            handler: GetDomainIntelligenceContext::class,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.permissions.view'),
            metadata: [
                'description' => 'Build an evidence-backed company-scoped context for one permitted WorkCore domain without executing mutations.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['domain'],
                    'additionalProperties' => false,
                    'properties' => [
                        'domain' => ['type' => 'string'],
                        'max_items' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500],
                    ],
                ],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.intelligence.domain.portfolio',
            handler: GetDomainIntelligencePortfolio::class,
            capability: 'workcore.intelligence',
            permission: (string) config('workcore.intelligence.permissions.view'),
            metadata: [
                'description' => 'Build a permission-filtered cross-domain operational intelligence portfolio for the active company.',
                'input_schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => ['domains' => ['type' => 'array', 'maxItems' => 7, 'items' => ['type' => 'string']]],
                ],
            ],
        ));

    }
}

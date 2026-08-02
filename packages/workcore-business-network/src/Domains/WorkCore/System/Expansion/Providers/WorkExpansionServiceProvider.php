<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Providers;

use App\Domains\WorkCore\System\Actions\ActionDefinition;
use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use App\Domains\WorkCore\System\Expansion\Actions\ActivateAdaptiveDomain;
use App\Domains\WorkCore\System\Expansion\Actions\ActivateAdaptiveFeature;
use App\Domains\WorkCore\System\Expansion\Actions\ApproveExpansion;
use App\Domains\WorkCore\System\Expansion\Actions\CreateAdaptiveRecord;
use App\Domains\WorkCore\System\Expansion\Actions\DisableAdaptiveFeature;
use App\Domains\WorkCore\System\Expansion\Actions\EnsureCapability;
use App\Domains\WorkCore\System\Expansion\Actions\ProcessAdaptiveFeatureEvent;
use App\Domains\WorkCore\System\Expansion\Actions\RollbackAdaptiveDomain;
use App\Domains\WorkCore\System\Expansion\Actions\UpdateAdaptiveRecord;
use App\Domains\WorkCore\System\Expansion\Contracts\AdaptiveFeatureRepositoryContract;
use App\Domains\WorkCore\System\Expansion\Contracts\AdaptiveReferenceValidatorContract;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionModelGatewayContract;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionPlannerContract;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionRepositoryContract;
use App\Domains\WorkCore\System\Expansion\Contracts\RecordSchemaInspectorContract;
use App\Domains\WorkCore\System\Expansion\Gateways\NullExpansionModelGateway;
use App\Domains\WorkCore\System\Expansion\Infrastructure\DatabaseAdaptiveReferenceValidator;
use App\Domains\WorkCore\System\Expansion\Infrastructure\LaravelRecordSchemaInspector;
use App\Domains\WorkCore\System\Expansion\Planners\HybridExpansionPlanner;
use App\Domains\WorkCore\System\Expansion\Planners\RuleBasedExpansionPlanner;
use App\Domains\WorkCore\System\Expansion\ReadModels\AssessCapabilityRequirement;
use App\Domains\WorkCore\System\Expansion\ReadModels\InspectAdaptiveFeature;
use App\Domains\WorkCore\System\Expansion\ReadModels\InspectAdaptiveRecord;
use App\Domains\WorkCore\System\Expansion\ReadModels\InspectExpansionRequest;
use App\Domains\WorkCore\System\Expansion\ReadModels\ListAdaptiveDomains;
use App\Domains\WorkCore\System\Expansion\ReadModels\ListAdaptiveDomainVersions;
use App\Domains\WorkCore\System\Expansion\ReadModels\ListAdaptiveFeatureRuns;
use App\Domains\WorkCore\System\Expansion\ReadModels\ListAdaptiveFeatures;
use App\Domains\WorkCore\System\Expansion\ReadModels\ListExpansionRequests;
use App\Domains\WorkCore\System\Expansion\ReadModels\SearchAdaptiveRecords;
use App\Domains\WorkCore\System\Expansion\ReadModels\SimulateAdaptiveFeature;
use App\Domains\WorkCore\System\Expansion\Repositories\DatabaseAdaptiveFeatureRepository;
use App\Domains\WorkCore\System\Expansion\Repositories\DatabaseExpansionRepository;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveBlueprintCompatibilityValidator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveBlueprintRollbackMerger;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveConditionEvaluator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveDomainBlueprintValidator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureBlueprintValidator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureDomainCompatibilityValidator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureEventProcessor;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureLoopGuard;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureSimulator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureValueResolver;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveRecordValidator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveStatusTransitionValidator;
use App\Domains\WorkCore\System\Expansion\Services\CapabilityGapAnalyzer;
use App\Domains\WorkCore\System\Expansion\Services\CapabilityInventoryBuilder;
use App\Domains\WorkCore\System\Expansion\Services\ConfiguredRecordSchemaScanner;
use App\Domains\WorkCore\System\Expansion\Services\RequirementNormalizer;
use App\Domains\WorkCore\System\ReadModels\ReadModelDefinition;
use App\Domains\WorkCore\System\ReadModels\ReadModelRegistry;
use Illuminate\Support\ServiceProvider;

final class WorkExpansionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RequirementNormalizer::class);
        $this->app->singleton(AdaptiveDomainBlueprintValidator::class, static fn (): AdaptiveDomainBlueprintValidator => new AdaptiveDomainBlueprintValidator(
            array_keys((array) config('workcore.expansion.reference_sources', [])),
            (int) config('workcore.expansion.safety.max_fields_per_domain', 50),
        ));
        $this->app->singleton(AdaptiveFeatureBlueprintValidator::class, static fn (): AdaptiveFeatureBlueprintValidator => new AdaptiveFeatureBlueprintValidator(
            (array) config('workcore.expansion.adaptive_features.allowed_actions', []),
            (int) config('workcore.expansion.adaptive_features.max_steps', 10),
            (int) config('workcore.expansion.adaptive_features.max_conditions', 20),
        ));
        $this->app->singleton(AdaptiveBlueprintCompatibilityValidator::class);
        $this->app->singleton(AdaptiveBlueprintRollbackMerger::class);
        $this->app->singleton(AdaptiveFeatureDomainCompatibilityValidator::class);
        $this->app->singleton(AdaptiveConditionEvaluator::class);
        $this->app->singleton(AdaptiveFeatureValueResolver::class);
        $this->app->singleton(AdaptiveFeatureLoopGuard::class);
        $this->app->singleton(AdaptiveFeatureSimulator::class);
        $this->app->singleton(AdaptiveRecordValidator::class);
        $this->app->singleton(AdaptiveStatusTransitionValidator::class);
        $this->app->singleton(CapabilityGapAnalyzer::class, static fn ($app): CapabilityGapAnalyzer => new CapabilityGapAnalyzer(
            $app->make(RequirementNormalizer::class),
            (float) config('workcore.expansion.match_threshold', 0.38),
        ));
        $this->app->singleton(AdaptiveReferenceValidatorContract::class, static fn ($app): AdaptiveReferenceValidatorContract => new DatabaseAdaptiveReferenceValidator(
            $app->make(\Illuminate\Database\ConnectionInterface::class),
            (array) config('workcore.expansion.reference_sources', []),
        ));
        $this->app->singleton(RecordSchemaInspectorContract::class, LaravelRecordSchemaInspector::class);
        $this->app->singleton(ConfiguredRecordSchemaScanner::class, static fn ($app): ConfiguredRecordSchemaScanner => new ConfiguredRecordSchemaScanner(
            $app->make(RecordSchemaInspectorContract::class),
            (array) config('workcore.expansion.schema_sources', []),
        ));
        $this->app->bind(ExpansionRepositoryContract::class, DatabaseExpansionRepository::class);
        $this->app->bind(AdaptiveFeatureRepositoryContract::class, DatabaseAdaptiveFeatureRepository::class);
        $this->app->singleton(AdaptiveFeatureEventProcessor::class);
        $this->app->bind(
            ExpansionModelGatewayContract::class,
            (string) config('workcore.expansion.model_gateway', NullExpansionModelGateway::class),
        );
        $this->app->singleton(RuleBasedExpansionPlanner::class);
        $this->app->singleton(HybridExpansionPlanner::class);
        $this->app->bind(
            ExpansionPlannerContract::class,
            (string) config('workcore.expansion.planner', HybridExpansionPlanner::class),
        );
        $this->app->singleton(CapabilityInventoryBuilder::class);

        $this->registerActions($this->app->make(BusinessActionRegistry::class));
        $this->registerReadModels($this->app->make(ReadModelRegistry::class));
    }

    private function registerActions(BusinessActionRegistry $actions): void
    {
        $actions->register(new ActionDefinition(
            key: 'workcore.capability.ensure',
            handler: EnsureCapability::class,
            risk: 'medium',
            requiresConfirmation: true,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.request'),
            metadata: [
                'description' => 'Assess a user requirement, reuse an existing feature, extend/create an adaptive domain, create a bounded adaptive feature, or produce a reviewed native-module specification.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['requirement'],
                    'properties' => [
                        'requirement' => ['type' => 'string', 'minLength' => 8, 'maxLength' => 4000],
                        'proposed_plan' => [
                            'type' => 'object',
                            'description' => 'Optional structured plan supplied by the calling AI. It is validated server-side and can never contain executable code.',
                            'properties' => [
                                'delivery_mode' => ['type' => 'string', 'enum' => ['adaptive_domain', 'adaptive_feature', 'native_module']],
                                'operation' => ['type' => 'string'],
                                'blueprint' => ['type' => 'object'],
                                'capability_key' => ['type' => 'string'],
                                'native_spec' => ['type' => 'object'],
                                'risk' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
                'output_modes' => ['existing_capability', 'extension_proposed', 'expansion_proposed', 'adaptive_domain_activated'],
            ],
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.capability.expansion.approve',
            handler: ApproveExpansion::class,
            risk: 'high',
            requiresConfirmation: true,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.approve'),
            metadata: [
                'description' => 'Approve an adaptive-domain or adaptive-feature plan for activation, or a native-module specification for reviewed development.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['request_public_id'],
                    'properties' => [
                        'request_public_id' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                    ],
                ],
            ],
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.adaptive_domain.activate',
            handler: ActivateAdaptiveDomain::class,
            risk: 'high',
            requiresConfirmation: true,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.activate'),
            metadata: [
                'description' => 'Activate an approved declarative adaptive domain or approved adaptive-domain extension.',
                'input_schema' => ['type' => 'object', 'required' => ['request_public_id'], 'properties' => ['request_public_id' => ['type' => 'string']]],
            ],
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.adaptive_domain.rollback',
            handler: RollbackAdaptiveDomain::class,
            risk: 'high',
            requiresConfirmation: true,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.rollback'),
            metadata: [
                'description' => 'Restore a previous adaptive-domain blueprint as a new audited compatibility-preserving version.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['domain', 'version'],
                    'properties' => [
                        'domain' => ['type' => 'string'],
                        'version' => ['type' => 'integer', 'minimum' => 1],
                    ],
                ],
            ],
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.adaptive_record.create',
            handler: CreateAdaptiveRecord::class,
            risk: 'medium',
            requiresConfirmation: true,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.records_create'),
            metadata: [
                'description' => 'Create a company-scoped record in an active AI-generated adaptive domain and run matching approved adaptive features.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['domain', 'data'],
                    'properties' => [
                        'domain' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'data' => ['type' => 'object'],
                    ],
                ],
            ],
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.adaptive_record.update',
            handler: UpdateAdaptiveRecord::class,
            risk: 'medium',
            requiresConfirmation: true,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.records_update'),
            metadata: [
                'description' => 'Patch an adaptive record, move it through an allowed status transition, and run matching approved adaptive features.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['record_public_id'],
                    'properties' => [
                        'record_public_id' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'data' => ['type' => 'object'],
                    ],
                ],
            ],
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.adaptive_feature.activate',
            handler: ActivateAdaptiveFeature::class,
            risk: 'high',
            requiresConfirmation: true,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.features_activate'),
            metadata: [
                'description' => 'Activate an approved bounded adaptive feature after validating its effects against the target domain.',
                'input_schema' => ['type' => 'object', 'required' => ['request_public_id'], 'properties' => ['request_public_id' => ['type' => 'string']]],
            ],
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.adaptive_feature.disable',
            handler: DisableAdaptiveFeature::class,
            risk: 'high',
            requiresConfirmation: true,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.features_disable'),
            metadata: [
                'description' => 'Disable an adaptive feature immediately through its audited kill switch.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['feature'],
                    'properties' => [
                        'feature' => ['type' => 'string'],
                        'reason' => ['type' => 'string', 'maxLength' => 1000],
                    ],
                ],
            ],
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.internal.adaptive_feature.process_event',
            handler: ProcessAdaptiveFeatureEvent::class,
            risk: 'medium',
            requiresConfirmation: false,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.features_execute'),
            metadata: [
                'internal_only' => true,
                'description' => 'Internal event/outbox bridge for processing approved adaptive features against native or adaptive WorkCore events.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['event', 'subject_type', 'subject_public_id'],
                    'properties' => [
                        'event' => ['type' => 'string'],
                        'subject_type' => ['type' => 'string'],
                        'subject_public_id' => ['type' => 'string'],
                        'context' => ['type' => 'object'],
                        'execution_depth' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10],
                    ],
                ],
            ],
        ));
    }

    private function registerReadModels(ReadModelRegistry $reads): void
    {
        $reads->register(new ReadModelDefinition(
            key: 'workcore.capability.assess',
            handler: AssessCapabilityRequirement::class,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.view'),
            metadata: [
                'description' => 'Classify a requirement as an existing feature, an extension of an existing domain, or a missing domain/feature without changing state.',
                'input_schema' => ['type' => 'object', 'required' => ['requirement'], 'properties' => ['requirement' => ['type' => 'string']]],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.capability.expansion.list',
            handler: ListExpansionRequests::class,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.view'),
            metadata: [
                'description' => 'List company-scoped capability expansion requests and their current lifecycle status.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'status' => ['type' => 'string'],
                    'delivery_mode' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ]],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.capability.expansion.inspect',
            handler: InspectExpansionRequest::class,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.view'),
            metadata: [
                'description' => 'Inspect an expansion assessment, validated plan, approvals and activation status.',
                'input_schema' => ['type' => 'object', 'required' => ['request_public_id'], 'properties' => ['request_public_id' => ['type' => 'string']]],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.adaptive_domain.list',
            handler: ListAdaptiveDomains::class,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.view'),
            metadata: [
                'description' => 'List company-scoped adaptive domains generated through the expansion workflow.',
                'input_schema' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string']]],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.adaptive_domain.versions',
            handler: ListAdaptiveDomainVersions::class,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.view'),
            metadata: [
                'description' => 'List immutable blueprint snapshots for an adaptive domain.',
                'input_schema' => ['type' => 'object', 'required' => ['domain'], 'properties' => [
                    'domain' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ]],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.adaptive_record.inspect',
            handler: InspectAdaptiveRecord::class,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.records_view'),
            metadata: [
                'description' => 'Inspect one company-scoped adaptive record and its active domain blueprint.',
                'input_schema' => ['type' => 'object', 'required' => ['record_public_id'], 'properties' => ['record_public_id' => ['type' => 'string']]],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.adaptive_record.search',
            handler: SearchAdaptiveRecords::class,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.records_view'),
            metadata: [
                'description' => 'Search records in an active company-scoped adaptive domain.',
                'input_schema' => ['type' => 'object', 'required' => ['domain'], 'properties' => [
                    'domain' => ['type' => 'string'],
                    'query' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ]],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.adaptive_feature.list',
            handler: ListAdaptiveFeatures::class,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.features_view'),
            metadata: [
                'description' => 'List active, disabled or draft company-scoped adaptive features and their trigger metadata.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'status' => ['type' => 'string'],
                    'event' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ]],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.adaptive_feature.inspect',
            handler: InspectAdaptiveFeature::class,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.features_view'),
            metadata: [
                'description' => 'Inspect an adaptive feature blueprint, trigger, bounded effects, limits and lifecycle state.',
                'input_schema' => ['type' => 'object', 'required' => ['feature'], 'properties' => ['feature' => ['type' => 'string']]],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.adaptive_feature.runs',
            handler: ListAdaptiveFeatureRuns::class,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.features_view'),
            metadata: [
                'description' => 'List audited executions, skips and failures for an adaptive feature.',
                'input_schema' => ['type' => 'object', 'required' => ['feature'], 'properties' => [
                    'feature' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                ]],
            ],
        ));
        $reads->register(new ReadModelDefinition(
            key: 'workcore.adaptive_feature.simulate',
            handler: SimulateAdaptiveFeature::class,
            capability: 'workcore.expansion',
            permission: (string) config('workcore.expansion.permissions.features_simulate'),
            metadata: [
                'description' => 'Dry-run an adaptive feature against supplied context and return resolved effects without applying side effects.',
                'input_schema' => ['type' => 'object', 'required' => ['feature', 'context'], 'properties' => [
                    'feature' => ['type' => 'string'],
                    'context' => ['type' => 'object'],
                    'execution_depth' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10],
                ]],
            ],
        ));
    }
}

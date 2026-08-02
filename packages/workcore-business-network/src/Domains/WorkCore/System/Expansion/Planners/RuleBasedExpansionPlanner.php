<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Planners;

use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionPlannerContract;
use App\Domains\WorkCore\System\Expansion\Data\CapabilityInventory;
use App\Domains\WorkCore\System\Expansion\Data\ExpansionAssessment;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveDomainBlueprintValidator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureBlueprintValidator;
use App\Domains\WorkCore\System\Expansion\Services\RequirementNormalizer;

final class RuleBasedExpansionPlanner implements ExpansionPlannerContract
{
    public function __construct(
        private RequirementNormalizer $normalizer,
        private AdaptiveDomainBlueprintValidator $blueprints,
        private ?AdaptiveFeatureBlueprintValidator $featureBlueprints = null,
    ) {}

    public function plan(
        string $requirement,
        CapabilityInventory $inventory,
        int $companyId,
        int $actorId,
        ?ExpansionAssessment $assessment = null,
        ?array $callingAiProposal = null,
    ): array {
        $tokens = $this->normalizer->tokens($requirement);
        $normalised = strtolower($requirement);
        $reasons = $this->escalationReasons($normalised, $tokens);
        $target = $assessment?->status === 'extend_existing' ? $assessment->matchedKey : null;
        $targetAdaptiveDomain = $target !== null ? $this->findAdaptiveDomain($inventory, $target) : null;
        $hardReasons = array_values(array_filter($reasons, static fn (string $reason): bool => $reason !== 'automation_or_notification'));

        if ($hardReasons !== []) {
            $reasons = $hardReasons;
            return [
                'planner' => 'rule_based',
                'delivery_mode' => 'native_module',
                'operation' => $target !== null ? 'extend_native_module' : 'create_native_module',
                'risk' => in_array('financial_side_effects', $reasons, true) || in_array('security_or_identity', $reasons, true) ? 'high' : 'medium',
                'requirement' => trim($requirement),
                'normalised_requirement' => $this->normalizer->normalise($requirement),
                'capability_key' => $target ?? 'workcore.' . str_replace('-', '_', $this->normalizer->slug($requirement, 48)),
                'target_capability' => $target,
                'escalation_reasons' => $reasons,
                'native_spec' => $this->nativeSpecification($requirement, $inventory, $target),
                'company_id' => $companyId,
                'requested_by_user_id' => $actorId,
                'runtime_executable' => false,
            ];
        }

        if (in_array('automation_or_notification', $reasons, true) && $this->featureBlueprints !== null) {
            $featureBlueprint = $this->featureBlueprints->validate($this->adaptiveFeatureBlueprint(
                $requirement,
                $tokens,
                $target,
                $targetAdaptiveDomain,
            ));

            return [
                'planner' => 'rule_based',
                'delivery_mode' => 'adaptive_feature',
                'operation' => 'create_adaptive_feature',
                'risk' => (string) $featureBlueprint['risk'],
                'requirement' => trim($requirement),
                'normalised_requirement' => $this->normalizer->normalise($requirement),
                'capability_key' => $featureBlueprint['capability_key'],
                'target_capability' => $target,
                'escalation_reasons' => [],
                'blueprint' => $featureBlueprint,
                'company_id' => $companyId,
                'requested_by_user_id' => $actorId,
                'runtime_executable' => false,
                'declarative_runtime' => true,
            ];
        }

        if (in_array('automation_or_notification', $reasons, true)) {
            return [
                'planner' => 'rule_based',
                'delivery_mode' => 'native_module',
                'operation' => $target !== null ? 'extend_native_module' : 'create_native_module',
                'risk' => 'medium',
                'requirement' => trim($requirement),
                'normalised_requirement' => $this->normalizer->normalise($requirement),
                'capability_key' => $target ?? 'workcore.' . str_replace('-', '_', $this->normalizer->slug($requirement, 48)),
                'target_capability' => $target,
                'escalation_reasons' => ['automation_or_notification'],
                'native_spec' => $this->nativeSpecification($requirement, $inventory, $target),
                'company_id' => $companyId,
                'requested_by_user_id' => $actorId,
                'runtime_executable' => false,
            ];
        }

        if ($targetAdaptiveDomain !== null) {
            $blueprint = $this->blueprints->validate($this->extendAdaptiveBlueprint($targetAdaptiveDomain, $requirement, $tokens));
            $operation = 'extend_adaptive_domain';
        } else {
            $blueprint = $this->blueprints->validate($this->adaptiveBlueprint($requirement, $tokens, $target));
            $operation = $target !== null ? 'extend_native_capability' : 'create_adaptive_domain';
        }

        return [
            'planner' => 'rule_based',
            'delivery_mode' => 'adaptive_domain',
            'operation' => $operation,
            'risk' => (string) $blueprint['risk'],
            'requirement' => trim($requirement),
            'normalised_requirement' => $this->normalizer->normalise($requirement),
            'capability_key' => $blueprint['capability_key'],
            'target_capability' => $target,
            'escalation_reasons' => [],
            'blueprint' => $blueprint,
            'company_id' => $companyId,
            'requested_by_user_id' => $actorId,
            'runtime_executable' => false,
        ];
    }

    /** @param array<int,string> $tokens @return array<int,string> */
    private function escalationReasons(string $requirement, array $tokens): array
    {
        $groups = [
            'external_integration' => ['api','webhook','integration','integrate','connect','xero','myob','quickbooks','stripe','paypal','bank feed','third party'],
            'financial_side_effects' => ['payment','payroll','tax','ledger','reconcile','reconciliation','refund','payout','banking','superannuation'],
            'security_or_identity' => ['authentication','authorisation','authorization','permission','encryption','secret','credential','biometric','identity','login'],
            'executable_or_deployment' => ['code','script','shell','execute','deployment','deploy','migration','database schema','server'],
            'hardware_or_device' => ['iot','bluetooth','sensor','hardware','device','gps','telemetry','firmware'],
            'regulated_decisioning' => ['medical diagnosis','legal decision','credit decision','insurance underwriting','safety certification'],
            'automation_or_notification' => ['reminder','reminders','notify','notification','notifications','alert','alerts','scheduled automation','background job','recurring automation'],
            'document_or_attachment' => ['document upload','document uploads','file upload','file uploads','attachment','attachments','signed document','signature capture'],
        ];

        $reasons = [];
        foreach ($groups as $reason => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($requirement, $needle) || in_array($needle, $tokens, true)) {
                    $reasons[] = $reason;
                    break;
                }
            }
        }

        return $reasons;
    }

    /** @param array<int,string> $tokens @return array<string,mixed> */
    private function adaptiveBlueprint(string $requirement, array $tokens, ?string $targetCapability): array
    {
        $slug = $this->normalizer->slug($requirement);
        $fields = [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
        ];

        if (array_intersect($tokens, ['inspection','inspect','test','audit','check','assessment']) !== []) {
            $fields[] = ['name' => 'performed_at', 'label' => 'Performed at', 'type' => 'datetime', 'required' => true];
            $fields[] = ['name' => 'outcome', 'label' => 'Outcome', 'type' => 'select', 'required' => true, 'options' => ['pass', 'fail', 'needs_attention']];
        } else {
            $fields[] = ['name' => 'occurred_at', 'label' => 'Occurred at', 'type' => 'datetime', 'required' => false];
        }

        if (array_intersect($tokens, ['premises','site','location']) !== []) {
            $fields[] = ['name' => 'premises_reference', 'label' => 'Premises', 'type' => 'reference', 'reference_type' => 'premises', 'required' => false];
        }
        if (array_intersect($tokens, ['customer','resident','tenant']) !== []) {
            $fields[] = ['name' => 'customer_reference', 'label' => 'Customer', 'type' => 'reference', 'reference_type' => 'customer', 'required' => false];
        }
        if (array_intersect($tokens, ['asset','equipment','tool','vehicle']) !== []) {
            $fields[] = ['name' => 'asset_reference', 'label' => 'Asset', 'type' => 'reference', 'reference_type' => 'asset', 'required' => false];
        }

        $fields[] = ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false];

        return [
            'slug' => $slug,
            'name' => $this->normalizer->title($requirement),
            'capability_key' => 'workcore.adaptive.' . str_replace('-', '_', $slug),
            'description' => trim($requirement),
            'risk' => 'low',
            'fields' => $fields,
            'statuses' => ['draft', 'active', 'archived'],
            'transitions' => [
                ['from' => 'draft', 'to' => 'active'],
                ['from' => 'active', 'to' => 'archived'],
                ['from' => 'archived', 'to' => 'active'],
            ],
            'version' => 1,
            'metadata' => array_filter([
                'generated_by' => 'workcore_expansion_rule_planner',
                'extends_capability' => $targetCapability,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }

    /** @param array<string,mixed> $domain @param array<int,string> $tokens @return array<string,mixed> */
    private function extendAdaptiveBlueprint(array $domain, string $requirement, array $tokens): array
    {
        $blueprint = (array) ($domain['blueprint'] ?? []);
        $identityTokens = $this->normalizer->tokens(implode(' ', [
            (string) ($domain['capability_key'] ?? ''),
            (string) ($domain['slug'] ?? ''),
            (string) ($domain['name'] ?? ''),
            (string) ($domain['description'] ?? ''),
        ]));
        $generic = ['add','additional','new','include','capture','record','read','reading','field','detail','data','value','extend','extension','also'];
        $fieldTokens = array_values(array_filter(
            array_diff($tokens, $identityTokens, $generic),
            static fn (string $token): bool => strlen($token) >= 2,
        ));
        if ($fieldTokens === []) {
            $fieldTokens = ['additional', 'detail'];
        }

        $fieldName = rtrim(substr(implode('_', array_slice($fieldTokens, 0, 3)), 0, 64), '_');
        $existingNames = array_map(
            static fn (array $field): string => (string) ($field['name'] ?? ''),
            array_values(array_filter((array) ($blueprint['fields'] ?? []), 'is_array')),
        );
        if (in_array($fieldName, $existingNames, true)) {
            $suffix = '_value';
            $fieldName = rtrim(substr($fieldName, 0, 64 - strlen($suffix)), '_') . $suffix;
        }

        $type = $this->inferFieldType($tokens);
        $field = [
            'name' => $fieldName,
            'label' => ucwords(str_replace('_', ' ', $fieldName)),
            'type' => $type,
            'required' => false,
            'description' => trim($requirement),
        ];
        if ($type === 'reference') {
            $field['reference_type'] = array_intersect($tokens, ['premises','customer','asset','worker']) !== []
                ? (string) array_values(array_intersect($tokens, ['premises','customer','asset','worker']))[0]
                : 'premises';
        }

        $blueprint['fields'] = [...array_values((array) ($blueprint['fields'] ?? [])), $field];
        $blueprint['version'] = max(1, (int) ($blueprint['version'] ?? 1)) + 1;
        $blueprint['metadata'] = [
            ...(array) ($blueprint['metadata'] ?? []),
            'last_extended_by' => 'workcore_expansion_rule_planner',
            'last_extension_requirement' => trim($requirement),
        ];

        return $blueprint;
    }

    /** @param array<int,string> $tokens */
    private function inferFieldType(array $tokens): string
    {
        if (array_intersect($tokens, ['date','expiry','expires','deadline','due']) !== []) {
            return 'date';
        }
        if (array_intersect($tokens, ['time','timestamp','datetime']) !== []) {
            return 'datetime';
        }
        if (array_intersect($tokens, ['amount','cost','price','fee','money']) !== []) {
            return 'money';
        }
        if (array_intersect($tokens, ['ppm','level','reading','measurement','quantity','count','score','rating','percentage','percent','ph']) !== []) {
            return 'number';
        }
        if (array_intersect($tokens, ['yes','no','flag','boolean','verified','approved']) !== []) {
            return 'boolean';
        }
        if (array_intersect($tokens, ['premises','customer','asset','worker']) !== []) {
            return 'reference';
        }

        return 'text';
    }

    /** @param array<int,string> $tokens @param array<string,mixed>|null $targetAdaptiveDomain @return array<string,mixed> */
    private function adaptiveFeatureBlueprint(
        string $requirement,
        array $tokens,
        ?string $targetCapability,
        ?array $targetAdaptiveDomain,
    ): array {
        $slug = $this->normalizer->slug($requirement, 72);
        $domainSlug = $targetAdaptiveDomain !== null ? (string) ($targetAdaptiveDomain['slug'] ?? '') : null;
        $steps = [];

        if (array_intersect($tokens, ['notify','notification','alert','reminder','remind']) !== []) {
            $steps[] = [
                'type' => 'notify',
                'notification_key' => 'workcore.adaptive.' . str_replace('-', '_', $slug),
                'recipient_type' => 'company_user',
                'recipient_id_from' => 'actor.id',
                'channel_hints' => ['in_app'],
                'payload' => [
                    'subject_public_id' => ['from' => 'record.public_id'],
                    'subject_status' => ['from' => 'record.status'],
                ],
            ];
        }

        $actionKey = null;
        if (array_intersect($tokens, ['work','order','job','follow','followup']) !== [] && $this->featureBlueprints?->allowsAction('workcore.work_order.create')) {
            $actionKey = 'workcore.work_order.create';
        } elseif (array_intersect($tokens, ['ticket','support','issue']) !== [] && $this->featureBlueprints?->allowsAction('workcore.support_ticket.create')) {
            $actionKey = 'workcore.support_ticket.create';
        }
        if ($actionKey !== null) {
            $steps[] = [
                'type' => 'invoke_action',
                'action_key' => $actionKey,
                'payload' => [
                    'summary' => ['value' => $this->normalizer->title($requirement)],
                ],
            ];
        }

        $steps[] = [
            'type' => 'emit_event',
            'event' => 'workcore.adaptive.' . str_replace('-', '.', $slug) . '.triggered',
            'payload' => [
                'subject_public_id' => ['from' => 'record.public_id'],
            ],
        ];

        $rules = [];
        if (array_intersect($tokens, ['fail','fails','failed','failure']) !== []) {
            $rules[] = ['path' => 'record.data.result', 'operator' => 'in', 'value' => ['fail', 'failed', 'failure']];
        } elseif (array_intersect($tokens, ['overdue','late','expired']) !== []) {
            $rules[] = ['path' => 'record.status', 'operator' => 'in', 'value' => ['overdue', 'late', 'expired']];
        }

        return [
            'slug' => $slug,
            'name' => $this->normalizer->title($requirement),
            'capability_key' => 'workcore.adaptive_feature.' . str_replace('-', '_', $slug),
            'description' => trim($requirement),
            'risk' => $actionKey === null ? 'low' : 'medium',
            'trigger' => array_filter([
                'event' => $targetAdaptiveDomain !== null ? 'workcore.adaptive_record.updated' : 'workcore.record.updated',
                'subject_type' => $targetAdaptiveDomain !== null ? 'adaptive_record' : 'record',
                'domain' => $domainSlug ?: $targetCapability,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'conditions' => ['mode' => 'all', 'rules' => $rules],
            'steps' => $steps,
            'limits' => [
                'max_executions_per_hour' => 100,
                'cooldown_seconds' => 60,
                'max_depth' => 3,
            ],
            'version' => 1,
            'metadata' => array_filter([
                'generated_by' => 'workcore_expansion_rule_planner',
                'extends_capability' => $targetCapability,
                'target_adaptive_domain' => $domainSlug,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }

    /** @return array<string,mixed>|null */
    private function findAdaptiveDomain(CapabilityInventory $inventory, string $target): ?array
    {
        foreach ($inventory->adaptiveDomains as $domain) {
            if (
                (string) ($domain['capability_key'] ?? '') === $target
                || (string) ($domain['slug'] ?? '') === $target
                || (string) ($domain['public_id'] ?? '') === $target
            ) {
                return $domain;
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function nativeSpecification(string $requirement, CapabilityInventory $inventory, ?string $targetCapability): array
    {
        $slug = $this->normalizer->slug($requirement, 48);
        $studly = str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
        $dependencyKeys = array_slice(array_keys($inventory->capabilities), 0, 8);

        return [
            'module_key' => str_replace('-', '_', $slug),
            'module_name' => $studly,
            'namespace' => "App\\Extensions\\WorkCore\\System\\Modules\\{$studly}",
            'requirement' => trim($requirement),
            'target_capability' => $targetCapability,
            'must_define' => [
                'capability_definition',
                'service_provider',
                'company_scoped_records',
                'business_actions',
                'read_models',
                'permissions',
                'domain_events',
                'idempotency_and_audit',
                'migrations',
                'unit_and_feature_tests',
                'rollback_notes',
            ],
            'integration_boundaries' => [
                'Use WorkCore TenantContextContract as the only company authority.',
                'Register writes through BusinessActionRegistry and reads through ReadModelRegistry.',
                'Expose tools through WorkCoreToolBridge metadata.',
                'Do not execute generated code or migrations inside the request lifecycle.',
            ],
            'candidate_dependencies' => $dependencyKeys,
            'acceptance_tests' => [
                'Cross-company access is rejected.',
                'Duplicate idempotency keys replay rather than duplicate writes.',
                'High-risk actions require confirmation and permission.',
                'Extension-disabled boot path performs no registration.',
            ],
            'build_only' => true,
            'runtime_executable' => false,
        ];
    }
}

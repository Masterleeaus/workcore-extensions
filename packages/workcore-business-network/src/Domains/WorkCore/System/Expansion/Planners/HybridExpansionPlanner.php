<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Planners;

use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionModelGatewayContract;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionPlannerContract;
use App\Domains\WorkCore\System\Expansion\Data\CapabilityInventory;
use App\Domains\WorkCore\System\Expansion\Data\ExpansionAssessment;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveBlueprintCompatibilityValidator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveDomainBlueprintValidator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureBlueprintValidator;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveFeatureDomainCompatibilityValidator;
use App\Domains\WorkCore\System\Intelligence\Proposals\ProposalIntake;
use InvalidArgumentException;
use Throwable;

final class HybridExpansionPlanner implements ExpansionPlannerContract
{
    public function __construct(
        private ExpansionModelGatewayContract $gateway,
        private RuleBasedExpansionPlanner $fallback,
        private AdaptiveDomainBlueprintValidator $blueprints,
        private AdaptiveBlueprintCompatibilityValidator $compatibility,
        private ?AdaptiveFeatureBlueprintValidator $featureBlueprints = null,
        private ?AdaptiveFeatureDomainCompatibilityValidator $featureCompatibility = null,
        private ?ProposalIntake $proposalIntake = null,
    ) {}

    public function plan(
        string $requirement,
        CapabilityInventory $inventory,
        int $companyId,
        int $actorId,
        ?ExpansionAssessment $assessment = null,
        ?array $callingAiProposal = null,
    ): array {
        if ($callingAiProposal !== null) {
            return $this->validateProposal(
                $callingAiProposal,
                $requirement,
                $inventory,
                $companyId,
                $actorId,
                $assessment,
                'calling_ai',
            );
        }

        try {
            $proposal = $this->gateway->propose([
                'instruction' => 'Return a structured WorkCore capability expansion plan. Prefer adaptive_domain for low-risk declarative records, adaptive_feature for bounded internal triggers/conditions/notifications/action intents, and native_module for integrations, privileged logic, regulated decisions or executable code. Generated code must never be returned or executed; native_module output is a reviewed build specification only.',
                'requirement' => $requirement,
                'company_id' => $companyId,
                'requested_by_user_id' => $actorId,
                'assessment' => $assessment?->toArray(),
                'inventory' => $inventory->toArray(),
                'allowed_delivery_modes' => ['adaptive_domain', 'adaptive_feature', 'native_module'],
                'allowed_operations' => ['create_adaptive_domain', 'extend_adaptive_domain', 'extend_native_capability', 'create_adaptive_feature', 'create_native_module', 'extend_native_module'],
            ]);

            if (is_array($proposal)) {
                return $this->validateProposal($proposal, $requirement, $inventory, $companyId, $actorId, $assessment, 'ai');
            }
        } catch (Throwable) {
            // The deterministic planner is the fail-closed fallback when the host AI is unavailable or malformed.
        }

        return $this->fallback->plan($requirement, $inventory, $companyId, $actorId, $assessment);
    }

    /** @param array<string,mixed> $proposal @return array<string,mixed> */
    private function validateProposal(
        array $proposal,
        string $requirement,
        CapabilityInventory $inventory,
        int $companyId,
        int $actorId,
        ?ExpansionAssessment $assessment,
        string $planner,
    ): array {
        if ($this->proposalIntake !== null) {
            $proposal = $this->proposalIntake->accept(
                json_encode($proposal, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ['type' => 'object', 'additionalProperties' => true, 'properties' => []],
                $companyId,
                $actorId,
                'capability_expansion',
            )->payload;
        }

        $encoded = json_encode($proposal, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > 100000) {
            throw new InvalidArgumentException('AI expansion proposal exceeds the 100 KB safety limit.');
        }

        $mode = (string) ($proposal['delivery_mode'] ?? '');
        if (! in_array($mode, ['adaptive_domain', 'adaptive_feature', 'native_module'], true)) {
            throw new InvalidArgumentException('AI expansion proposal contains an invalid delivery mode.');
        }

        $target = $assessment?->status === 'extend_existing' ? $assessment->matchedKey : null;
        $operation = (string) ($proposal['operation'] ?? $this->defaultOperation($mode, $target, $assessment));
        $allowedOperations = match ($mode) {
            'adaptive_domain' => ['create_adaptive_domain', 'extend_adaptive_domain', 'extend_native_capability'],
            'adaptive_feature' => ['create_adaptive_feature'],
            default => ['create_native_module', 'extend_native_module'],
        };
        if (! in_array($operation, $allowedOperations, true)) {
            throw new InvalidArgumentException('AI expansion proposal contains an invalid operation for its delivery mode.');
        }

        $validated = [
            ...$proposal,
            'planner' => $planner,
            'delivery_mode' => $mode,
            'operation' => $operation,
            'requirement' => trim($requirement),
            'target_capability' => $target,
            'company_id' => $companyId,
            'requested_by_user_id' => $actorId,
            'runtime_executable' => false,
        ];

        if ($mode === 'adaptive_domain') {
            $blueprint = (array) ($proposal['blueprint'] ?? []);
            if ($target !== null && $assessment?->matchType === 'adaptive_domain') {
                $existing = $this->findAdaptiveDomain($inventory, $target)
                    ?? throw new InvalidArgumentException('The adaptive domain selected for extension is no longer available.');
                if (
                    (string) ($blueprint['slug'] ?? '') !== (string) ($existing['slug'] ?? '')
                    || (string) ($blueprint['capability_key'] ?? '') !== (string) ($existing['capability_key'] ?? '')
                ) {
                    throw new InvalidArgumentException('An adaptive-domain extension must preserve the target domain slug and capability key.');
                }
                $operation = 'extend_adaptive_domain';
            } elseif ($target !== null) {
                $metadata = is_array($blueprint['metadata'] ?? null) ? $blueprint['metadata'] : [];
                $blueprint['metadata'] = [...$metadata, 'extends_capability' => $target];
                $operation = 'extend_native_capability';
            }

            $blueprint = $this->blueprints->validate($blueprint);
            if ($operation === 'extend_adaptive_domain') {
                $blueprint = $this->compatibility->validateExtension((array) ($existing['blueprint'] ?? []), $blueprint);
            }
            $validated['operation'] = $operation;
            $validated['blueprint'] = $blueprint;
            $validated['capability_key'] = $blueprint['capability_key'];
            $validated['risk'] = $blueprint['risk'];
            $validated['escalation_reasons'] = [];
            return $validated;
        }

        if ($mode === 'adaptive_feature') {
            if ($this->featureBlueprints === null) {
                throw new InvalidArgumentException('Adaptive feature proposals are disabled because no feature validator is configured.');
            }
            $blueprint = $this->featureBlueprints->validate((array) ($proposal['blueprint'] ?? []));
            if (($blueprint['trigger']['subject_type'] ?? null) === 'adaptive_record') {
                $domainIdentifier = (string) ($blueprint['trigger']['domain'] ?? $target ?? '');
                $existing = $this->findAdaptiveDomain($inventory, $domainIdentifier)
                    ?? throw new InvalidArgumentException('Adaptive feature proposal target domain is not available.');
                $this->featureCompatibility?->validate($blueprint, (array) ($existing['blueprint'] ?? []));
            }
            $validated['operation'] = 'create_adaptive_feature';
            $validated['blueprint'] = $blueprint;
            $validated['capability_key'] = $blueprint['capability_key'];
            $validated['risk'] = $blueprint['risk'];
            $validated['escalation_reasons'] = [];
            $validated['declarative_runtime'] = true;
            return $validated;
        }

        $capabilityKey = trim((string) ($proposal['capability_key'] ?? $target ?? ''));
        if (! preg_match('/^workcore\.[a-z][a-z0-9_.]{2,100}$/', $capabilityKey)) {
            throw new InvalidArgumentException('Native AI expansion proposals require a valid workcore.* capability key.');
        }
        if ($target !== null && $capabilityKey !== $target) {
            throw new InvalidArgumentException('A native extension proposal must preserve its target capability.');
        }
        $nativeSpec = (array) ($proposal['native_spec'] ?? []);
        if ($nativeSpec === []) {
            throw new InvalidArgumentException('Native AI expansion proposals require a native specification.');
        }
        $this->assertNativeSpecificationIsNonExecutable($nativeSpec);

        $validated['operation'] = $target !== null ? 'extend_native_module' : 'create_native_module';
        $validated['capability_key'] = $capabilityKey;
        $validated['native_spec'] = [...$nativeSpec, 'build_only' => true, 'runtime_executable' => false];
        $validated['risk'] = in_array((string) ($proposal['risk'] ?? ''), ['medium', 'high', 'critical'], true)
            ? (string) $proposal['risk']
            : 'high';
        $validated['escalation_reasons'] = array_values((array) ($proposal['escalation_reasons'] ?? ['ai_native_escalation']));

        return $validated;
    }

    private function defaultOperation(string $mode, ?string $target, ?ExpansionAssessment $assessment): string
    {
        if ($mode === 'native_module') {
            return $target !== null ? 'extend_native_module' : 'create_native_module';
        }
        if ($mode === 'adaptive_feature') {
            return 'create_adaptive_feature';
        }
        if ($target === null) {
            return 'create_adaptive_domain';
        }

        return $assessment?->matchType === 'adaptive_domain' ? 'extend_adaptive_domain' : 'extend_native_capability';
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

    /** @param array<string,mixed> $spec */
    private function assertNativeSpecificationIsNonExecutable(array $spec): void
    {
        $forbiddenKeys = ['code','source_code','script','shell','sql','command','commands','file_contents','migration_body','executable_payload'];
        $forbiddenText = ['<?php','<?=','shell_exec','passthru(','proc_open','popen(','eval(','system(','exec(','drop table','truncate table','#!/bin/'];

        $scan = static function (mixed $value, ?string $key = null) use (&$scan, $forbiddenKeys, $forbiddenText): void {
            if ($key !== null && in_array(strtolower($key), $forbiddenKeys, true)) {
                throw new InvalidArgumentException("Native expansion specifications cannot contain executable key [{$key}].");
            }
            if (is_array($value)) {
                foreach ($value as $childKey => $child) {
                    $scan($child, is_string($childKey) ? $childKey : null);
                }
                return;
            }
            if (! is_string($value)) {
                return;
            }
            $normalised = strtolower($value);
            foreach ($forbiddenText as $needle) {
                if (str_contains($normalised, $needle)) {
                    throw new InvalidArgumentException('Native expansion specifications cannot contain executable source or database commands.');
                }
            }
        };

        $scan($spec);
    }
}

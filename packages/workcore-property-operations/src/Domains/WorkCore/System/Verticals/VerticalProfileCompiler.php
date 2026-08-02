<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals;

use App\Domains\WorkCore\System\Verticals\Codebooks\CodebookRegistry;

final class VerticalProfileCompiler
{
    private CodebookRegistry $codebooks;

    public function __construct(private VerticalRegistry $registry, ?CodebookRegistry $codebooks = null)
    {
        $this->codebooks = $codebooks ?? new CodebookRegistry(require dirname(__DIR__, 2) . '/config/vertical_codebooks.php');
    }

    /**
     * @param list<string> $addOns
     * @param array<string,bool> $featureOverrides
     * @param array<string,string> $terminologyOverrides
     */
    public function compile(
        ?string $primaryVertical,
        array $addOns = [],
        array $featureOverrides = [],
        array $terminologyOverrides = [],
    ): CompiledVerticalProfile {
        if ($primaryVertical === null || trim($primaryVertical) === '') {
            return new CompiledVerticalProfile(null, [], [], [], [], ['mode' => 'legacy']);
        }

        $this->registry->validateDependencies();
        $seen = [];
        $order = [];

        foreach (array_values(array_unique($addOns)) as $addOn) {
            if ($addOn === $primaryVertical) {
                continue;
            }
            $this->appendWithDependencies($addOn, $seen, $order);
        }
        $this->appendWithDependencies($primaryVertical, $seen, $order);

        $capabilities = [];
        $features = [];
        $terminology = [];
        $compiledCodebooks = [];
        $compiledWorkflows = [];
        $packMetadata = [];

        foreach ($order as $key) {
            $definition = $this->registry->get($key);
            foreach ($definition->capabilities as $capability) {
                $capabilities[$capability] = true;
            }
            $features = array_replace($features, $definition->features);
            $terminology = array_replace($terminology, $definition->terminology);
            foreach ($definition->codebooks as $codebookKey => $rule) {
                $compiledCodebooks[$codebookKey] = $this->mergeCodebookRule((array) ($compiledCodebooks[$codebookKey] ?? []), (array) $rule, $codebookKey);
            }
            foreach ($definition->workflows as $workflowKey => $workflow) {
                $compiledWorkflows[$workflowKey] = $this->mergeWorkflow((array) ($compiledWorkflows[$workflowKey] ?? []), (array) $workflow);
            }
            $packMetadata[$key] = [
                'label' => $definition->label,
                'version' => $definition->version,
                'type' => $definition->type,
            ];
        }

        $features = array_replace($features, $featureOverrides);
        $terminology = array_replace($terminology, $terminologyOverrides);

        $capabilityKeys = array_keys($capabilities);
        sort($capabilityKeys, SORT_STRING);

        return new CompiledVerticalProfile(
            primaryVertical: $primaryVertical,
            packKeys: $order,
            capabilities: $capabilityKeys,
            features: $features,
            terminology: $terminology,
            metadata: ['mode' => 'vertical', 'packs' => $packMetadata],
            codebooks: $compiledCodebooks,
            workflows: $compiledWorkflows,
        );
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $incoming @return array<string,mixed> */
    private function mergeCodebookRule(array $current, array $incoming, string $key): array
    {
        $include = array_values(array_unique(array_merge(
            array_map('strval', (array) ($current['include'] ?? [])),
            array_map('strval', (array) ($incoming['include'] ?? [])),
        )));
        $exclude = array_values(array_unique(array_merge(
            array_map('strval', (array) ($current['exclude'] ?? [])),
            array_map('strval', (array) ($incoming['exclude'] ?? [])),
        )));
        $this->codebooks->assertKnown($key, array_values(array_unique(array_merge($include, $exclude))));
        $labels = array_replace((array) ($current['labels'] ?? []), (array) ($incoming['labels'] ?? []));
        foreach ($labels as $value => $label) {
            $this->codebooks->assertKnown($key, [(string) $value]);
            if (! is_string($label) || trim($label) === '') {
                throw new \InvalidArgumentException("Invalid label for [{$key}.{$value}].");
            }
        }
        $default = array_key_exists('default', $incoming) ? (string) $incoming['default'] : ($current['default'] ?? null);
        if ($default !== null) {
            $this->codebooks->assertKnown($key, [(string) $default]);
        }
        return ['include' => $include, 'exclude' => $exclude, 'labels' => $labels, 'default' => $default];
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $incoming @return array<string,mixed> */
    private function mergeWorkflow(array $current, array $incoming): array
    {
        $transitions = (array) ($current['transitions'] ?? []);
        foreach ((array) ($incoming['transitions'] ?? []) as $from => $targets) {
            $transitions[(string) $from] = array_values(array_unique(array_merge(
                array_map('strval', (array) ($transitions[(string) $from] ?? [])),
                array_map('strval', (array) $targets),
            )));
        }
        return array_replace($current, $incoming, ['transitions' => $transitions]);
    }

    /** @param array<string,bool> $seen @param list<string> $order */
    private function appendWithDependencies(string $key, array &$seen, array &$order): void
    {
        if (isset($seen[$key])) {
            return;
        }

        $definition = $this->registry->get($key);
        foreach ($definition->extends as $dependency) {
            $this->appendWithDependencies($dependency, $seen, $order);
        }

        $seen[$key] = true;
        $order[] = $key;
    }
}

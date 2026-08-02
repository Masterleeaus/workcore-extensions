<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

use RuntimeException;

final class AdaptiveFeatureSimulator
{
    public function __construct(
        private AdaptiveFeatureBlueprintValidator $blueprints,
        private AdaptiveConditionEvaluator $conditions,
        private AdaptiveFeatureValueResolver $values,
    ) {}

    /** @param array<string,mixed> $blueprint @param array<string,mixed> $context @return array<string,mixed> */
    public function simulate(array $blueprint, array $context, int $executionDepth = 0): array
    {
        $blueprint = $this->blueprints->validate($blueprint);
        $maxDepth = (int) $blueprint['limits']['max_depth'];
        if ($executionDepth >= $maxDepth) {
            throw new RuntimeException("Adaptive feature execution depth [{$executionDepth}] exceeds the allowed depth [{$maxDepth}].");
        }

        if (! $this->conditions->matches((array) $blueprint['conditions'], $context)) {
            return [
                'status' => 'conditions_not_met',
                'feature' => ['slug' => $blueprint['slug'], 'capability_key' => $blueprint['capability_key'], 'version' => $blueprint['version']],
                'effects' => [],
                'side_effects_applied' => false,
            ];
        }

        $effects = [];
        foreach ((array) $blueprint['steps'] as $position => $step) {
            $effects[] = $this->effect((array) $step, $context, $position);
        }

        return [
            'status' => 'matched',
            'feature' => ['slug' => $blueprint['slug'], 'capability_key' => $blueprint['capability_key'], 'version' => $blueprint['version']],
            'effects' => $effects,
            'side_effects_applied' => false,
        ];
    }

    /** @param array<string,mixed> $step @param array<string,mixed> $context @return array<string,mixed> */
    private function effect(array $step, array $context, int $position): array
    {
        $type = (string) $step['type'];
        $base = ['position' => $position + 1, 'type' => $type];
        return match ($type) {
            'set_field' => [...$base, 'field' => $step['field'], 'value' => array_key_exists('value', $step) ? $step['value'] : $this->values->resolve(['from' => $step['value_from']], $context)],
            'transition_status' => [...$base, 'status' => $step['status']],
            'notify' => [
                ...$base,
                'notification_key' => $step['notification_key'],
                'recipient_type' => $step['recipient_type'],
                'recipient_id' => (string) $this->values->resolve(['from' => $step['recipient_id_from']], $context),
                'channel_hints' => $step['channel_hints'],
                'payload' => $this->values->resolveMap((array) $step['payload'], $context),
            ],
            'invoke_action' => [...$base, 'action_key' => $step['action_key'], 'payload' => $this->values->resolveMap((array) $step['payload'], $context)],
            'emit_event' => [...$base, 'event' => $step['event'], 'payload' => $this->values->resolveMap((array) $step['payload'], $context)],
            default => throw new RuntimeException("Unsupported adaptive feature step [{$type}]."),
        };
    }
}

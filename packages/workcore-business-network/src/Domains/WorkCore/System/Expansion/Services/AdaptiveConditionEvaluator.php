<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

final class AdaptiveConditionEvaluator
{
    /** @param array<string,mixed> $conditions @param array<string,mixed> $context */
    public function matches(array $conditions, array $context): bool
    {
        $mode = (string) ($conditions['mode'] ?? 'all');
        $rules = array_values((array) ($conditions['rules'] ?? []));
        if ($rules === []) {
            return true;
        }

        $results = array_map(fn (mixed $rule): bool => is_array($rule) && $this->matchesRule($rule, $context), $rules);
        return $mode === 'any' ? in_array(true, $results, true) : ! in_array(false, $results, true);
    }

    /** @param array<string,mixed> $rule @param array<string,mixed> $context */
    private function matchesRule(array $rule, array $context): bool
    {
        [$exists, $actual] = $this->readPath($context, (string) ($rule['path'] ?? ''));
        $operator = (string) ($rule['operator'] ?? 'equals');
        $expected = $rule['value'] ?? null;

        return match ($operator) {
            'exists' => $exists,
            'not_exists' => ! $exists,
            'equals' => $exists && $actual === $expected,
            'not_equals' => ! $exists || $actual !== $expected,
            'in' => $exists && is_array($expected) && in_array($actual, $expected, true),
            'not_in' => ! $exists || (is_array($expected) && ! in_array($actual, $expected, true)),
            'gt' => $exists && is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'gte' => $exists && is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            'lt' => $exists && is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'lte' => $exists && is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
            'contains' => $exists && (is_string($actual) ? str_contains($actual, (string) $expected) : (is_array($actual) && in_array($expected, $actual, true))),
            default => false,
        };
    }

    /** @param array<string,mixed> $context @return array{0:bool,1:mixed} */
    private function readPath(array $context, string $path): array
    {
        $cursor = $context;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return [false, null];
            }
            $cursor = $cursor[$segment];
        }
        return [true, $cursor];
    }
}

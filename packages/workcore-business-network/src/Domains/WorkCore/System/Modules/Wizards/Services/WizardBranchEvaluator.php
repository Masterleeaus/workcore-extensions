<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Wizards\Services;

final class WizardBranchEvaluator
{
    /** @param array<string,mixed> $condition @param array<string,mixed> $answers */
    public function matches(array $condition, array $answers): bool
    {
        if ($condition === [] || isset($condition['hint'])) return true;
        if (isset($condition['all'])) return $this->every((array) $condition['all'], $answers);
        if (isset($condition['any'])) return $this->some((array) $condition['any'], $answers);
        $actual = $answers[(string) ($condition['field'] ?? '')] ?? null;
        $expected = $condition['value'] ?? null;
        return match ((string) ($condition['operator'] ?? 'equals')) {
            'equals' => $actual === $expected,
            'not_equals' => $actual !== $expected,
            'includes' => is_array($actual) && in_array($expected, $actual, true),
            'truthy' => (bool) $actual,
            'falsy' => ! (bool) $actual,
            'present' => $actual !== null && $actual !== '' && $actual !== [],
            default => false,
        };
    }
    private function every(array $conditions, array $answers): bool { foreach ($conditions as $c) if (! $this->matches((array) $c, $answers)) return false; return true; }
    private function some(array $conditions, array $answers): bool { foreach ($conditions as $c) if ($this->matches((array) $c, $answers)) return true; return false; }
}

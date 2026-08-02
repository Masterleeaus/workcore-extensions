<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals;

final class TerminologyResolver
{
    /** @param array<string,string|int|float> $replacements */
    public function term(
        CompiledVerticalProfile $profile,
        string $key,
        ?string $fallback = null,
        array $replacements = [],
    ): string {
        $value = $profile->term($key, $fallback);
        if ($replacements === []) {
            return $value;
        }

        $tokens = [];
        foreach ($replacements as $name => $replacement) {
            $tokens[':' . $name] = (string) $replacement;
        }

        return strtr($value, $tokens);
    }
}

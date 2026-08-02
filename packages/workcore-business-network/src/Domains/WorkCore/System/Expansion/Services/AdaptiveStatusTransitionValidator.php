<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

use InvalidArgumentException;

final class AdaptiveStatusTransitionValidator
{
    public function __construct(private AdaptiveDomainBlueprintValidator $blueprints) {}

    /** @param array<string,mixed> $blueprint */
    public function validate(array $blueprint, string $from, string $to): string
    {
        $blueprint = $this->blueprints->validate($blueprint);
        $from = strtolower(trim($from));
        $to = strtolower(trim($to));
        $statuses = (array) $blueprint['statuses'];

        if (! in_array($from, $statuses, true) || ! in_array($to, $statuses, true)) {
            throw new InvalidArgumentException("Adaptive status transition [{$from} -> {$to}] references a status not defined by the domain blueprint.");
        }
        if ($from === $to) {
            return $to;
        }

        $transitions = (array) ($blueprint['transitions'] ?? []);
        if ($transitions === []) {
            return $to;
        }
        foreach ($transitions as $transition) {
            if (
                is_array($transition)
                && (string) ($transition['from'] ?? '') === $from
                && (string) ($transition['to'] ?? '') === $to
            ) {
                return $to;
            }
        }

        throw new InvalidArgumentException("Adaptive status transition [{$from} -> {$to}] is not allowed by the domain blueprint.");
    }
}

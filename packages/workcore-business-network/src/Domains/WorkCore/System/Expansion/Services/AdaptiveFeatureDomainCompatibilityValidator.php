<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

use InvalidArgumentException;

final class AdaptiveFeatureDomainCompatibilityValidator
{
    /** @param array<string,mixed> $feature @param array<string,mixed> $domain */
    public function validate(array $feature, array $domain): void
    {
        $trigger = (array) ($feature['trigger'] ?? []);
        $triggerDomain = (string) ($trigger['domain'] ?? '');
        $domainSlug = (string) ($domain['slug'] ?? '');
        $capabilityKey = (string) ($domain['capability_key'] ?? '');
        if ($triggerDomain !== '' && ! in_array($triggerDomain, [$domainSlug, $capabilityKey], true)) {
            throw new InvalidArgumentException('Adaptive feature trigger domain does not match the selected adaptive domain.');
        }

        $fields = [];
        foreach ((array) ($domain['fields'] ?? []) as $field) {
            if (is_array($field) && isset($field['name'])) {
                $fields[(string) $field['name']] = true;
            }
        }
        $statuses = array_values(array_map('strval', (array) ($domain['statuses'] ?? [])));
        $transitions = array_map(
            static fn (mixed $transition): string => is_array($transition)
                ? (string) ($transition['from'] ?? '') . '>' . (string) ($transition['to'] ?? '')
                : '',
            (array) ($domain['transitions'] ?? []),
        );

        foreach ((array) ($feature['steps'] ?? []) as $step) {
            if (! is_array($step)) {
                continue;
            }
            if (($step['type'] ?? null) === 'set_field') {
                $field = (string) ($step['field'] ?? '');
                if (! isset($fields[$field])) {
                    throw new InvalidArgumentException("Adaptive feature set_field targets unknown field [{$field}].");
                }
            }
            if (($step['type'] ?? null) === 'transition_status') {
                $status = (string) ($step['status'] ?? '');
                if (! in_array($status, $statuses, true)) {
                    throw new InvalidArgumentException("Adaptive feature transition targets unknown status [{$status}].");
                }
                $hasTransition = false;
                foreach ($transitions as $transition) {
                    if (str_ends_with($transition, '>' . $status)) {
                        $hasTransition = true;
                        break;
                    }
                }
                if (! $hasTransition) {
                    throw new InvalidArgumentException("Adaptive feature transition to [{$status}] is not declared by the target domain.");
                }
            }
        }
    }
}

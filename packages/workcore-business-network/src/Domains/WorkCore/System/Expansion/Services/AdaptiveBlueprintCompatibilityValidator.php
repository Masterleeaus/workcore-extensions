<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

use InvalidArgumentException;

final class AdaptiveBlueprintCompatibilityValidator
{
    public function __construct(private AdaptiveDomainBlueprintValidator $blueprints) {}

    /** @param array<string,mixed> $current @param array<string,mixed> $proposed @return array<string,mixed> */
    public function validateExtension(array $current, array $proposed): array
    {
        $current = $this->blueprints->validate($current);
        $proposed = $this->blueprints->validate($proposed);

        if ($current['slug'] !== $proposed['slug'] || $current['capability_key'] !== $proposed['capability_key']) {
            throw new InvalidArgumentException('Adaptive extensions must preserve the domain slug and capability key.');
        }

        $proposedFields = [];
        foreach ($proposed['fields'] as $field) {
            $proposedFields[$field['name']] = $field;
        }
        foreach ($current['fields'] as $field) {
            $name = (string) $field['name'];
            if (! isset($proposedFields[$name])) {
                throw new InvalidArgumentException("Adaptive extensions cannot remove existing field [{$name}].");
            }
            $candidate = $proposedFields[$name];
            if ($candidate['type'] !== $field['type']) {
                throw new InvalidArgumentException("Adaptive extensions cannot change field type for [{$name}].");
            }
            if (($field['type'] ?? null) === 'reference' && ($candidate['reference_type'] ?? null) !== ($field['reference_type'] ?? null)) {
                throw new InvalidArgumentException("Adaptive extensions cannot change reference type for [{$name}].");
            }
            if (! ($field['required'] ?? false) && ($candidate['required'] ?? false)) {
                throw new InvalidArgumentException("Adaptive extensions cannot make existing optional field [{$name}] required.");
            }
            if (in_array($field['type'], ['select', 'multiselect'], true)) {
                $removed = array_diff((array) ($field['options'] ?? []), (array) ($candidate['options'] ?? []));
                if ($removed !== []) {
                    throw new InvalidArgumentException("Adaptive extensions cannot remove existing options from [{$name}].");
                }
            }
        }

        $currentFieldNames = array_fill_keys(array_map(
            static fn (array $field): string => (string) $field['name'],
            (array) $current['fields'],
        ), true);
        foreach ($proposed['fields'] as $field) {
            if (! isset($currentFieldNames[$field['name']]) && ($field['required'] ?? false)) {
                throw new InvalidArgumentException("Adaptive extensions cannot add required field [{$field['name']}] without a reviewed data migration.");
            }
        }

        $removedStatuses = array_diff((array) $current['statuses'], (array) $proposed['statuses']);
        if ($removedStatuses !== []) {
            throw new InvalidArgumentException('Adaptive extensions cannot remove existing statuses.');
        }

        $proposedTransitions = array_fill_keys(array_map(
            static fn (array $transition): string => $transition['from'] . '>' . $transition['to'],
            (array) $proposed['transitions'],
        ), true);
        foreach ((array) $current['transitions'] as $transition) {
            $key = $transition['from'] . '>' . $transition['to'];
            if (! isset($proposedTransitions[$key])) {
                throw new InvalidArgumentException("Adaptive extensions cannot remove existing transition [{$transition['from']} -> {$transition['to']}].");
            }
        }

        return $proposed;
    }
}

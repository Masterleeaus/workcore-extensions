<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

use InvalidArgumentException;

final class AdaptiveBlueprintRollbackMerger
{
    public function __construct(private AdaptiveDomainBlueprintValidator $blueprints) {}

    /** @param array<string,mixed> $current @param array<string,mixed> $snapshot @return array<string,mixed> */
    public function merge(array $current, array $snapshot, int $restoredFromVersion, int $actorId): array
    {
        $current = $this->blueprints->validate($current);
        $snapshot = $this->blueprints->validate($snapshot);
        if ($current['slug'] !== $snapshot['slug'] || $current['capability_key'] !== $snapshot['capability_key']) {
            throw new InvalidArgumentException('Adaptive rollback snapshot does not belong to the selected domain.');
        }

        $currentFields = [];
        foreach ($current['fields'] as $field) {
            $currentFields[$field['name']] = $field;
        }
        $mergedFields = [];
        $snapshotNames = [];
        foreach ($snapshot['fields'] as $snapshotField) {
            $name = (string) $snapshotField['name'];
            $snapshotNames[$name] = true;
            $currentField = $currentFields[$name] ?? null;
            if (is_array($currentField)) {
                if ($currentField['type'] !== $snapshotField['type']) {
                    $snapshotField = $currentField;
                } else {
                    $snapshotField['required'] = (bool) ($snapshotField['required'] ?? false)
                        && (bool) ($currentField['required'] ?? false);
                    if (in_array($snapshotField['type'], ['select', 'multiselect'], true)) {
                        $snapshotField['options'] = array_values(array_unique([
                            ...(array) ($snapshotField['options'] ?? []),
                            ...(array) ($currentField['options'] ?? []),
                        ]));
                    }
                }
            }
            $mergedFields[] = $snapshotField;
        }

        $retainedFields = [];
        foreach ($current['fields'] as $field) {
            $name = (string) $field['name'];
            if (isset($snapshotNames[$name])) {
                continue;
            }
            $field['required'] = false;
            $mergedFields[] = $field;
            $retainedFields[] = $name;
        }

        $retainedStatuses = array_values(array_diff($current['statuses'], $snapshot['statuses']));
        $statuses = array_values(array_unique([...$snapshot['statuses'], ...$current['statuses']]));
        $transitionKeys = [];
        $transitions = [];
        foreach ([...$snapshot['transitions'], ...$current['transitions']] as $transition) {
            $key = $transition['from'] . '>' . $transition['to'];
            if (isset($transitionKeys[$key])) {
                continue;
            }
            $transitionKeys[$key] = true;
            $transitions[] = $transition;
        }

        $snapshot['fields'] = $mergedFields;
        $snapshot['statuses'] = $statuses;
        $snapshot['transitions'] = $transitions;
        $snapshot['metadata'] = [
            ...(array) ($snapshot['metadata'] ?? []),
            'restored_from_version' => $restoredFromVersion,
            'restored_by_user_id' => $actorId,
            'retained_fields_after_rollback' => $retainedFields,
            'retained_statuses_after_rollback' => $retainedStatuses,
        ];

        return $this->blueprints->validate($snapshot);
    }
}

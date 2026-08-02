<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Operations;

final class WorkflowProgress
{
    public static function normaliseCurrentStage(array $stages, ?string $currentStage): ?string
    {
        $keys = self::stageKeys($stages);
        if ($keys === []) {
            return null;
        }

        return in_array($currentStage, $keys, true) ? $currentStage : $keys[0];
    }

    public static function nextStageKey(array $stages, ?string $currentStage): ?string
    {
        $keys = self::stageKeys($stages);
        if ($keys === []) {
            return null;
        }

        $current = self::normaliseCurrentStage($stages, $currentStage);
        $index = array_search($current, $keys, true);
        if ($index === false || !array_key_exists($index + 1, $keys)) {
            return null;
        }

        return $keys[$index + 1];
    }

    public static function completionPercent(array $stages, ?string $currentStage): int
    {
        $keys = self::stageKeys($stages);
        $count = count($keys);
        if ($count <= 1) {
            return $count === 1 ? 100 : 0;
        }

        $current = self::normaliseCurrentStage($stages, $currentStage);
        $index = array_search($current, $keys, true);
        if ($index === false) {
            return 0;
        }

        return (int) round(($index / ($count - 1)) * 100);
    }

    public static function stageKeys(array $stages): array
    {
        $keys = [];
        foreach ($stages as $stage) {
            $key = is_array($stage) ? ($stage['key'] ?? null) : null;
            if (is_string($key) && $key !== '' && !in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}

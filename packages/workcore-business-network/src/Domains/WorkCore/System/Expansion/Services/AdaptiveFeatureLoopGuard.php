<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

use DateTimeImmutable;
use RuntimeException;

final class AdaptiveFeatureLoopGuard
{
    /** @param array<string,mixed> $context */
    public function idempotencyKey(string $featurePublicId, string $event, string $subjectPublicId, array $context, int $version): string
    {
        $identity = [
            'feature' => $featurePublicId,
            'version' => $version,
            'event' => $event,
            'subject' => $subjectPublicId,
            'event_id' => $context['event']['public_id'] ?? $context['event']['id'] ?? null,
            'record_version' => $context['record']['version'] ?? $context['record']['updated_at'] ?? null,
        ];
        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
    }

    /** @param array<string,mixed> $blueprint */
    public function assertWithinLimits(array $blueprint, int $executionDepth, int $executionsLastHour, ?DateTimeImmutable $lastExecutedAt): void
    {
        $limits = (array) ($blueprint['limits'] ?? []);
        $maxDepth = max(1, (int) ($limits['max_depth'] ?? 2));
        if ($executionDepth >= $maxDepth) {
            throw new RuntimeException("Adaptive feature execution depth [{$executionDepth}] reached the configured depth limit [{$maxDepth}].");
        }

        $maxPerHour = max(1, (int) ($limits['max_executions_per_hour'] ?? 100));
        if ($executionsLastHour >= $maxPerHour) {
            throw new RuntimeException("Adaptive feature hourly execution limit [{$maxPerHour}] has been reached.");
        }

        $cooldown = max(0, (int) ($limits['cooldown_seconds'] ?? 0));
        if ($cooldown > 0 && $lastExecutedAt !== null && $lastExecutedAt > new DateTimeImmutable("-{$cooldown} seconds")) {
            throw new RuntimeException("Adaptive feature cooldown [{$cooldown} seconds] has not elapsed.");
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Dispatch\Services;

use InvalidArgumentException;

final class DispatchStatusPolicy
{
    /** @var array<string,list<string>> */
    private const TRANSITIONS = [
        'assigned' => ['dispatched', 'cancelled'],
        'dispatched' => ['en_route', 'cancelled', 'failed'],
        'en_route' => ['arrived', 'cancelled', 'failed'],
        'arrived' => ['in_progress', 'cancelled', 'failed'],
        'in_progress' => ['completed', 'cancelled', 'failed'],
        'completed' => [],
        'cancelled' => ['assigned'],
        'failed' => ['assigned'],
    ];

    public function assertTransition(string $fromStatus, string $toStatus, ?string $reason): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }
        if (! isset(self::TRANSITIONS[$fromStatus]) || ! in_array($toStatus, self::TRANSITIONS[$fromStatus], true)) {
            throw new InvalidArgumentException("Dispatch cannot move from {$fromStatus} to {$toStatus}.");
        }
        if (in_array($toStatus, ['cancelled', 'failed'], true) && trim((string) $reason) === '') {
            throw new InvalidArgumentException("A reason is required when a dispatch is {$toStatus}.");
        }
        if (in_array($fromStatus, ['cancelled', 'failed'], true) && $toStatus === 'assigned' && trim((string) $reason) === '') {
            throw new InvalidArgumentException('A reason is required when reopening a dispatch.');
        }
    }
}

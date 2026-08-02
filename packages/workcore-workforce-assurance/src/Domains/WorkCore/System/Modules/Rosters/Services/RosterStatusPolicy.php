<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Services;

use InvalidArgumentException;

final class RosterStatusPolicy
{
    /** @var array<string,list<string>> */
    private const TRANSITIONS = [
        'draft' => ['published', 'cancelled'],
        'published' => ['closed', 'cancelled'],
        'cancelled' => ['draft'],
        'closed' => [],
    ];

    public function assertTransition(string $from, string $to, ?string $reason): void
    {
        if ($from === $to) {
            return;
        }
        if (! isset(self::TRANSITIONS[$from]) || ! in_array($to, self::TRANSITIONS[$from], true)) {
            throw new InvalidArgumentException("Roster cannot transition from {$from} to {$to}.");
        }
        if (($to === 'cancelled' || $from === 'cancelled') && trim((string) $reason) === '') {
            throw new InvalidArgumentException('Cancelling or reopening a roster requires a reason.');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Support\Services;

use InvalidArgumentException;

final class TicketPriorityPolicy
{
    /** @var array<string,int> */
    private const DUE_MINUTES = [
        'low' => 4320,
        'normal' => 1440,
        'high' => 480,
        'urgent' => 120,
        'emergency' => 30,
    ];

    public function assertValid(string $priority): void
    {
        if (! array_key_exists($priority, self::DUE_MINUTES)) {
            throw new InvalidArgumentException('Unsupported support ticket priority.');
        }
    }

    public function defaultDueMinutes(string $priority): int
    {
        $this->assertValid($priority);
        return self::DUE_MINUTES[$priority];
    }

    public function isEmergency(string $priority): bool
    {
        return $priority === 'emergency';
    }
}

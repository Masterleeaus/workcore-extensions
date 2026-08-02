<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\RecurringServices\Services;

use InvalidArgumentException;

final class ServiceAgreementStatusPolicy
{
    private const ALLOWED = [
        'draft' => ['active', 'cancelled'],
        'active' => ['paused', 'completed', 'cancelled', 'expired'],
        'paused' => ['active', 'completed', 'cancelled'],
        'completed' => ['active'],
        'expired' => ['active'],
        'cancelled' => ['active'],
    ];

    public function assertTransition(string $from, string $to, ?string $reason): void
    {
        if ($from === $to) return;
        if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
            throw new InvalidArgumentException("Unsupported recurring-service agreement transition from {$from} to {$to}.");
        }
        if (in_array($to, ['paused', 'cancelled'], true) && trim((string) $reason) === '') {
            throw new InvalidArgumentException('A reason is required when pausing or cancelling a service agreement.');
        }
        if (in_array($from, ['completed', 'expired', 'cancelled'], true) && $to === 'active' && trim((string) $reason) === '') {
            throw new InvalidArgumentException('A reason is required when reactivating a closed service agreement.');
        }
    }

    public function assertRenewal(string $status, ?string $currentEnd, string $newEnd): void
    {
        if (! in_array($status, ['active', 'paused', 'completed', 'expired'], true)) {
            throw new InvalidArgumentException('Only active, paused, completed or expired agreements can be renewed.');
        }
        if ($currentEnd !== null && strtotime($newEnd) <= strtotime($currentEnd)) {
            throw new InvalidArgumentException('The renewed end date must be later than the current end date.');
        }
    }
}

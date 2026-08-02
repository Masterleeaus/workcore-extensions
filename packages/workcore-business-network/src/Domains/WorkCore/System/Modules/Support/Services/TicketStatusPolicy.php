<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Support\Services;

use InvalidArgumentException;

final class TicketStatusPolicy
{
    /** @var array<string,list<string>> */
    private const TRANSITIONS = [
        'open' => ['triaged', 'assigned', 'in_progress', 'waiting_requester', 'waiting_external', 'resolved', 'cancelled'],
        'triaged' => ['assigned', 'in_progress', 'waiting_requester', 'waiting_external', 'resolved', 'cancelled'],
        'assigned' => ['triaged', 'in_progress', 'waiting_requester', 'waiting_external', 'resolved', 'cancelled'],
        'in_progress' => ['waiting_requester', 'waiting_external', 'resolved', 'cancelled'],
        'waiting_requester' => ['in_progress', 'resolved', 'cancelled'],
        'waiting_external' => ['in_progress', 'resolved', 'cancelled'],
        'resolved' => ['closed', 'in_progress'],
        'closed' => ['in_progress'],
        'cancelled' => ['open'],
    ];

    public function assertTransition(string $from, string $to, ?string $reason): void
    {
        if ($from === $to) {
            return;
        }
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidArgumentException("Support ticket cannot transition from {$from} to {$to}.");
        }
        if (in_array($to, ['resolved', 'cancelled'], true) && trim((string) $reason) === '') {
            throw new InvalidArgumentException("A reason is required when marking a support ticket {$to}.");
        }
        if (((in_array($from, ['resolved', 'closed'], true) && $to === 'in_progress') || ($from === 'cancelled' && $to === 'open')) && trim((string) $reason) === '') {
            throw new InvalidArgumentException('A reason is required when reopening a resolved, closed or cancelled support ticket.');
        }
    }
}

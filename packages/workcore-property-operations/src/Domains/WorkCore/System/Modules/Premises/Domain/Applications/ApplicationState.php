<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Applications;

final class ApplicationState
{
    public const STATUSES = ['draft', 'submitted', 'screening', 'information_required', 'shortlisted', 'offered', 'accepted', 'rejected', 'withdrawn', 'converted', 'cancelled'];
    private const TRANSITIONS = [
        'draft' => ['submitted', 'withdrawn', 'cancelled'],
        'submitted' => ['screening', 'information_required', 'withdrawn', 'rejected', 'cancelled'],
        'screening' => ['information_required', 'shortlisted', 'rejected', 'withdrawn', 'cancelled'],
        'information_required' => ['submitted', 'screening', 'withdrawn', 'cancelled'],
        'shortlisted' => ['offered', 'rejected', 'withdrawn', 'cancelled'],
        'offered' => ['accepted', 'rejected', 'withdrawn', 'cancelled'],
        'accepted' => ['converted', 'withdrawn', 'cancelled'],
        'rejected' => [],
        'withdrawn' => [],
        'converted' => [],
        'cancelled' => [],
    ];
    public static function canTransition(string $from, string $to): bool { return in_array($to, self::TRANSITIONS[$from] ?? [], true); }
    public static function isTerminal(string $status): bool { return in_array($status, ['rejected', 'withdrawn', 'converted', 'cancelled'], true); }
}

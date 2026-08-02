<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Communications;

final class NoticeState
{
    public const STATUSES = ['draft', 'pending_approval', 'approved', 'issued', 'acknowledged', 'withdrawn', 'expired', 'rejected'];
    private const TRANSITIONS = [
        'draft' => ['pending_approval', 'withdrawn'],
        'pending_approval' => ['approved', 'rejected', 'withdrawn'],
        'approved' => ['issued', 'withdrawn'],
        'issued' => ['acknowledged', 'withdrawn', 'expired'],
        'acknowledged' => [],
        'withdrawn' => [],
        'expired' => [],
        'rejected' => [],
    ];
    public static function canTransition(string $from, string $to): bool { return in_array($to, self::TRANSITIONS[$from] ?? [], true); }
    public static function isTerminal(string $status): bool { return in_array($status, ['acknowledged', 'withdrawn', 'expired', 'rejected'], true); }
}

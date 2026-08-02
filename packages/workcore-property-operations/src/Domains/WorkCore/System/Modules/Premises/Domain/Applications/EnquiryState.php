<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Applications;

final class EnquiryState
{
    public const STATUSES = ['new', 'contacted', 'qualified', 'disqualified', 'converted', 'closed'];
    private const TRANSITIONS = [
        'new' => ['contacted', 'qualified', 'disqualified', 'closed'],
        'contacted' => ['qualified', 'disqualified', 'closed'],
        'qualified' => ['converted', 'disqualified', 'closed'],
        'disqualified' => [],
        'converted' => [],
        'closed' => [],
    ];
    public static function canTransition(string $from, string $to): bool { return in_array($to, self::TRANSITIONS[$from] ?? [], true); }
    public static function isTerminal(string $status): bool { return in_array($status, ['disqualified', 'converted', 'closed'], true); }
}

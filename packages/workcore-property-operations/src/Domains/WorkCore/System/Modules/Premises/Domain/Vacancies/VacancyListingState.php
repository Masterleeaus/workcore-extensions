<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Vacancies;

final class VacancyListingState
{
    public const STATUSES = ['draft', 'pending_approval', 'approved', 'published', 'paused', 'closed', 'rejected'];
    public const TERMINAL_STATUSES = ['closed', 'rejected'];

    private const TRANSITIONS = [
        'draft' => ['pending_approval', 'closed'],
        'pending_approval' => ['approved', 'rejected', 'draft', 'closed'],
        'approved' => ['published', 'draft', 'closed'],
        'published' => ['paused', 'closed'],
        'paused' => ['published', 'closed'],
        'closed' => [],
        'rejected' => [],
    ];

    public static function canCreate(string $status): bool
    {
        return $status === 'draft';
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isPublic(string $status): bool
    {
        return $status === 'published';
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATUSES, true);
    }
}

<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Vacancies;

final class VacancyPublicationState
{
    public const STATUSES = ['pending', 'published', 'failed', 'withdrawn'];
    public const TERMINAL_STATUSES = ['published', 'failed', 'withdrawn'];

    private const TRANSITIONS = [
        'pending' => ['published', 'failed', 'withdrawn'],
        'published' => ['withdrawn'],
        'failed' => [],
        'withdrawn' => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATUSES, true);
    }
}

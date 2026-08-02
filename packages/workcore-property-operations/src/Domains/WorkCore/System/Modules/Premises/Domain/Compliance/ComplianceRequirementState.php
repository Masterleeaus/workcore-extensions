<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Compliance;

final class ComplianceRequirementState
{
    public const STATUSES = ['active', 'paused', 'retired'];

    private const TRANSITIONS = [
        'active' => ['paused', 'retired'],
        'paused' => ['active', 'retired'],
        'retired' => [],
    ];

    public static function canCreate(string $status): bool
    {
        return in_array($status, ['active', 'paused'], true);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function allowedTransitions(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public static function isTerminal(string $status): bool
    {
        return $status === 'retired';
    }
}

<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Support\Enums;

class RiskLevels
{
    public const LOW = 'low';
    public const MEDIUM = 'medium';
    public const HIGH = 'high';

    public static function all(): array
    {
        return [self::LOW, self::MEDIUM, self::HIGH];
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Ageing;

enum AgeingBucket: string
{
    case Current = 'current';
    case Days1To30 = '1_30';
    case Days31To60 = '31_60';
    case Days61To90 = '61_90';
    case Days90Plus = '90_plus';

    public static function fromDaysOverdue(int $days): self
    {
        return match (true) {
            $days <= 0 => self::Current,
            $days <= 30 => self::Days1To30,
            $days <= 60 => self::Days31To60,
            $days <= 90 => self::Days61To90,
            default => self::Days90Plus,
        };
    }
}

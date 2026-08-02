<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Portfolio;

class OwnershipSharePolicy
{
    public static function canAdd(float $activeShare, float $newShare): bool
    {
        if ($activeShare < 0 || $activeShare > 100 || $newShare <= 0 || $newShare > 100) {
            return false;
        }

        return round($activeShare + $newShare, 4) <= 100.0;
    }
}

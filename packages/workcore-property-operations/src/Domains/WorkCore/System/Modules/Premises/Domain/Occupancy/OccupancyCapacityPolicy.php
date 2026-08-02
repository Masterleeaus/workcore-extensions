<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Occupancy;

final class OccupancyCapacityPolicy
{
    public static function canAllocate(?int $capacity, int $used, int $requested): bool
    {
        if ($requested < 1 || $used < 0) {
            return false;
        }

        if ($capacity === null) {
            return true;
        }

        return ($used + $requested) <= $capacity;
    }
}

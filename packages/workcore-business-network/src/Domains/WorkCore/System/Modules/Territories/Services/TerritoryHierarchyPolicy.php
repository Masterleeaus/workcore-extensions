<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Territories\Services;

use InvalidArgumentException;

final class TerritoryHierarchyPolicy
{
    private const ASSIGNABLE_TYPES = ['worker','premises','customer','service','asset','roster','stock_location'];
    private const STATUSES = ['active','inactive','archived'];

    public function assertParentCompany(int $companyId, int $parentCompanyId, string $kind): void
    {
        if ($companyId < 1 || $parentCompanyId !== $companyId) {
            throw new InvalidArgumentException(ucfirst($kind).' does not belong to the active company.');
        }
    }

    public function assertAssignmentType(string $type): void
    {
        if (! in_array($type, self::ASSIGNABLE_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported territory assignment type.');
        }
    }

    public function assertStatus(string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported territory status.');
        }
    }
}

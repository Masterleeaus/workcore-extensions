<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Budgeting;

use InvalidArgumentException;

final readonly class BudgetApprovalThreshold
{
    public function __construct(
        public int $minimumMinor,
        public string $permission,
    ) {
        if ($minimumMinor < 0 || trim($permission) === '') {
            throw new InvalidArgumentException('A non-negative threshold and permission are required.');
        }
    }
}

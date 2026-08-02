<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Budgeting;

final class BudgetApprovalPolicy
{
    /** @param list<BudgetApprovalThreshold> $thresholds */
    public function requiredPermission(int $amountMinor, array $thresholds): ?string
    {
        usort($thresholds, static fn (BudgetApprovalThreshold $a, BudgetApprovalThreshold $b): int => $a->minimumMinor <=> $b->minimumMinor);
        $required = null;
        foreach ($thresholds as $threshold) {
            if ($amountMinor >= $threshold->minimumMinor) {
                $required = $threshold->permission;
            }
        }
        return $required;
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\TrustAccounting\Domain;

final class TrustLedgerPolicy
{
    public static function mayPostReceipt(bool $isOperatingAccount, string $accountType): bool
    {
        return ! $isOperatingAccount && $accountType === 'trust';
    }

    public static function mayReleaseDisbursement(int $approvalCount, int $requiredApprovals): bool
    {
        return $requiredApprovals >= 1 && $approvalCount >= $requiredApprovals;
    }

    public static function correctionMode(): string
    {
        return 'reversal_and_replacement';
    }
}

<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Agreements;

final class AgreementFinanceLinkType
{
    public const TYPES = [
        'billing_account',
        'recurring_charge',
        'deposit',
        'bond',
        'invoice',
        'payment',
        'arrears_case',
        'credit_note',
        'refund',
    ];

    public static function isSupported(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    public static function isCompatibilityBillingReference(string $type): bool
    {
        return $type === 'billing_account';
    }

    public static function isCompatibilityDepositReference(string $type): bool
    {
        return in_array($type, ['deposit', 'bond'], true);
    }
}

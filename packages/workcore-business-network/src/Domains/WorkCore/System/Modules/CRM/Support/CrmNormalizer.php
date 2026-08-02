<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Support;

final class CrmNormalizer
{
    public static function email(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));
        return $value === '' ? null : $value;
    }

    public static function phone(?string $value): ?string
    {
        $value = preg_replace('/[^0-9+]/', '', trim((string) $value)) ?? '';
        return $value === '' ? null : $value;
    }
}

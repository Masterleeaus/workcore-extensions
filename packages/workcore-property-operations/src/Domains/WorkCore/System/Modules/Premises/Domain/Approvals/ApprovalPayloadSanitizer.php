<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Approvals;

class ApprovalPayloadSanitizer
{
    private const FORBIDDEN_KEYS = [
        'password', 'secret', 'token', 'token_hash', 'pin', 'access_code',
        'lockbox_code', 'bank_account', 'account_number', 'card_number',
    ];

    public static function sanitize(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            $normalised = strtolower((string) $key);
            if (self::isSensitiveKey($normalised)) {
                continue;
            }

            $clean[$key] = is_array($value) ? self::sanitize($value) : $value;
        }

        return $clean;
    }

    private static function isSensitiveKey(string $key): bool
    {
        if (in_array($key, self::FORBIDDEN_KEYS, true)) {
            return true;
        }

        return (bool) preg_match(
            '/(?:password|secret|token|pin|access[_-]?code|lockbox|bank[_-]?account|account[_-]?number|card[_-]?number)/',
            $key
        );
    }
}

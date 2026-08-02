<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Support;

use InvalidArgumentException;
use JsonSerializable;

final class CanonicalJson
{
    /** @param array<string|int, mixed> $payload */
    public static function encode(array $payload): string
    {
        return json_encode(
            self::normalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_float($value)) {
            throw new InvalidArgumentException('Canonical financial payloads must not contain floating-point numbers.');
        }

        if ($value instanceof JsonSerializable) {
            return self::normalize($value->jsonSerialize());
        }

        if (is_object($value)) {
            throw new InvalidArgumentException('Canonical payloads must contain arrays, scalar values, or JsonSerializable objects.');
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[(string) $key] = self::normalize($item);
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }
}

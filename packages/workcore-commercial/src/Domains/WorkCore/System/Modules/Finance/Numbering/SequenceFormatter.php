<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Numbering;

use InvalidArgumentException;

final class SequenceFormatter
{
    public function format(string $prefix, int $value, int $padding, string $suffix = ''): string
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Sequence values cannot be negative.');
        }

        if ($padding < 1 || $padding > 20) {
            throw new InvalidArgumentException('Sequence padding must be between 1 and 20 digits.');
        }

        if (strlen($prefix) > 32 || strlen($suffix) > 32) {
            throw new InvalidArgumentException('Sequence prefix and suffix must not exceed 32 characters.');
        }

        return $prefix . str_pad((string) $value, $padding, '0', STR_PAD_LEFT) . $suffix;
    }
}

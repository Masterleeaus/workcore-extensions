<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Shared;

use InvalidArgumentException;

final readonly class DecimalMoneyParser
{
    /** @param array<string, int> $minorUnits */
    public function __construct(private array $minorUnits)
    {
    }

    public function parse(string $decimal, string|CurrencyCode $currency): Money
    {
        $code = $currency instanceof CurrencyCode ? $currency : new CurrencyCode($currency);
        $scale = $this->minorUnits[$code->value] ?? null;

        if (! is_int($scale) || $scale < 0 || $scale > 6) {
            throw new InvalidArgumentException("No valid minor-unit definition exists for {$code->value}.");
        }

        $value = trim($decimal);
        if (preg_match('/^(-?)(0|[1-9][0-9]*)(?:\.([0-9]+))?$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException('Money value must be a plain decimal string without separators or exponent notation.');
        }

        $negative = $matches[1] === '-';
        $whole = $matches[2];
        $fraction = $matches[3] ?? '';

        if (strlen($fraction) > $scale) {
            throw new InvalidArgumentException("Money value has more than {$scale} decimal places for {$code->value}.");
        }

        $digits = ltrim($whole . str_pad($fraction, $scale, '0'), '0');
        $digits = $digits === '' ? '0' : $digits;
        $limit = (string) PHP_INT_MAX;

        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            throw new InvalidArgumentException('Money value exceeds the supported integer range.');
        }

        $minor = (int) $digits;
        if ($negative && $minor !== 0) {
            $minor *= -1;
        }

        return new Money($minor, $code);
    }
}

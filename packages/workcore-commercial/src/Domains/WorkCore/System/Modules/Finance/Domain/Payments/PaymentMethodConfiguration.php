<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Payments;

use InvalidArgumentException;

final readonly class PaymentMethodConfiguration
{
    /** @param array<string,mixed> $values */
    public function __construct(
        public PaymentMethodKey $method,
        public array $values,
        public bool $enabled = true,
    ) {
        if (! $enabled) {
            throw new InvalidArgumentException("Payment method {$method->value} is disabled.");
        }
    }

    public function requireString(string $key): string
    {
        $value = $this->values[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Payment configuration requires {$key}.");
        }
        return trim($value);
    }
}

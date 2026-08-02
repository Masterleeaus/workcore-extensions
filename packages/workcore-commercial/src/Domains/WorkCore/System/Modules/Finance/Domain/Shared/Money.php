<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Shared;

use DomainException;

final readonly class Money
{
    public function __construct(
        public int $minorAmount,
        public CurrencyCode $currency,
    ) {
    }

    public static function ofMinor(int $minorAmount, string|CurrencyCode $currency): self
    {
        return new self(
            $minorAmount,
            $currency instanceof CurrencyCode ? $currency : new CurrencyCode($currency),
        );
    }

    public static function zero(string|CurrencyCode $currency): self
    {
        return self::ofMinor(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorAmount + $other->minorAmount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorAmount - $other->minorAmount, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->minorAmount, $this->currency);
    }

    public function absolute(): self
    {
        return $this->minorAmount < 0 ? $this->negate() : $this;
    }

    public function isZero(): bool
    {
        return $this->minorAmount === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorAmount < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency->equals($other->currency)
            && $this->minorAmount === $other->minorAmount;
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minorAmount <=> $other->minorAmount;
    }

    /** @return array{minor_amount:int,currency:string} */
    public function toArray(): array
    {
        return [
            'minor_amount' => $this->minorAmount,
            'currency' => $this->currency->value,
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw new DomainException(sprintf(
                'Cannot combine %s and %s money values.',
                $this->currency->value,
                $other->currency->value,
            ));
        }
    }
}

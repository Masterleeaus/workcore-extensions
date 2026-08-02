<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Idempotency;

use InvalidArgumentException;

final readonly class IdempotencyKey
{
    private function __construct(public string $hash)
    {
    }

    public static function fromRaw(string $raw): self
    {
        $value = trim($raw);
        if (strlen($value) < 16 || strlen($value) > 512) {
            throw new InvalidArgumentException('Idempotency keys must contain between 16 and 512 characters.');
        }

        return new self(hash('sha256', $value));
    }

    /** @return array{idempotency_key_hash:string} */
    public function toArray(): array
    {
        return ['idempotency_key_hash' => $this->hash];
    }
}

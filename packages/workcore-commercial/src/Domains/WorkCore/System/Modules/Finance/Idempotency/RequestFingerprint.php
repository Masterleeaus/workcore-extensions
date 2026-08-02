<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Idempotency;

use App\Domains\WorkCore\System\Modules\Finance\Support\CanonicalJson;

final readonly class RequestFingerprint
{
    private function __construct(public string $hash)
    {
    }

    /** @param array<string|int, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(hash('sha256', CanonicalJson::encode($payload)));
    }
}

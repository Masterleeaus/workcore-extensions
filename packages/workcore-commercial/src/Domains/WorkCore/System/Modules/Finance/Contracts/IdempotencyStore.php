<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Idempotency\IdempotencyClaim;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\IdempotencyKey;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\RequestFingerprint;

interface IdempotencyStore
{
    public function claim(
        string $companyId,
        string $actionKey,
        IdempotencyKey $key,
        RequestFingerprint $fingerprint,
    ): IdempotencyClaim;

    /** @param array<string, mixed> $resultPayload */
    public function complete(
        string $companyId,
        string $actionKey,
        IdempotencyKey $key,
        array $resultPayload,
    ): void;

    public function fail(
        string $companyId,
        string $actionKey,
        IdempotencyKey $key,
        string $failureCode,
    ): void;
}

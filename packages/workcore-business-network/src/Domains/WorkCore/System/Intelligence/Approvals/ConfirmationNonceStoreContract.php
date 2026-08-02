<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Approvals;

interface ConfirmationNonceStoreContract
{
    public function reserve(string $nonce, int $companyId, int $actorId, string $action, int $expiresAt): void;

    public function consume(string $nonce, int $companyId, int $actorId, string $action, int $now): bool;
}

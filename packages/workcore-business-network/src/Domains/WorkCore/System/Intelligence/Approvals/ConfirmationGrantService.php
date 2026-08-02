<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Approvals;

final class ConfirmationGrantService
{
    public function __construct(
        private ConfirmationGrantSigner $signer,
        private ConfirmationNonceStoreContract $nonces,
        private int $ttlSeconds = 300,
    ) {}

    public function issue(int $companyId, int $actorId, string $action, string $payloadHash, string $idempotencyKey): string
    {
        $nonce = bin2hex(random_bytes(24));
        $expiresAt = time() + max(30, $this->ttlSeconds);
        $this->nonces->reserve($nonce, $companyId, $actorId, $action, $expiresAt);

        return $this->signer->issue($companyId, $actorId, $action, $payloadHash, $idempotencyKey, $expiresAt, $nonce);
    }
}

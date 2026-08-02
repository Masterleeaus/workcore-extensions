<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Approvals;

use Illuminate\Database\ConnectionInterface;
use RuntimeException;

final class DatabaseConfirmationNonceStore implements ConfirmationNonceStoreContract
{
    public function __construct(private ConnectionInterface $db) {}

    public function reserve(string $nonce, int $companyId, int $actorId, string $action, int $expiresAt): void
    {
        $inserted = $this->db->table('tz_ai_confirmation_nonces')->insertOrIgnore([
            'nonce_hash' => hash('sha256', $nonce),
            'company_id' => $companyId,
            'actor_id' => $actorId,
            'action_key' => $action,
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted !== 1) {
            throw new RuntimeException('Confirmation nonce already exists.');
        }
    }

    public function consume(string $nonce, int $companyId, int $actorId, string $action, int $now): bool
    {
        return $this->db->table('tz_ai_confirmation_nonces')
            ->where('nonce_hash', hash('sha256', $nonce))
            ->where('company_id', $companyId)
            ->where('actor_id', $actorId)
            ->where('action_key', $action)
            ->whereNull('consumed_at')
            ->where('expires_at', '>=', date('Y-m-d H:i:s', $now))
            ->update(['consumed_at' => now(), 'updated_at' => now()]) === 1;
    }
}

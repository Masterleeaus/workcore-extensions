<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Approvals;

use InvalidArgumentException;

final class ConfirmationGrantSigner
{
    public function __construct(private string $secret, private string $keyId = 'primary')
    {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('Confirmation signing secret must contain at least 32 bytes.');
        }
    }

    public function issue(
        int $companyId,
        int $actorId,
        string $action,
        string $payloadHash,
        string $idempotencyKey,
        int $expiresAt,
        string $nonce,
    ): string {
        if ($companyId < 1 || $actorId < 1 || trim($action) === '' || trim($payloadHash) === '' || trim($idempotencyKey) === '' || trim($nonce) === '') {
            throw new InvalidArgumentException('A confirmation grant requires complete binding claims.');
        }
        $claims = [
            'v' => 1,
            'kid' => $this->keyId,
            'company_id' => $companyId,
            'actor_id' => $actorId,
            'action' => $action,
            'payload_hash' => $payloadHash,
            'idempotency_key' => $idempotencyKey,
            'expires_at' => $expiresAt,
            'nonce' => $nonce,
        ];
        $payload = self::base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $signature = self::base64UrlEncode(hash_hmac('sha256', $payload, $this->secret, true));

        return $payload . '.' . $signature;
    }

    public function verify(string $token, int $companyId, int $actorId, string $action, string $payloadHash, string $idempotencyKey, ?int $now = null): bool
    {
        $claims = $this->claims($token);
        if ($claims === null) {
            return false;
        }
        $now ??= time();

        return (int) ($claims['v'] ?? 0) === 1
            && hash_equals((string) ($claims['kid'] ?? ''), $this->keyId)
            && (int) ($claims['company_id'] ?? 0) === $companyId
            && (int) ($claims['actor_id'] ?? 0) === $actorId
            && hash_equals((string) ($claims['action'] ?? ''), $action)
            && hash_equals((string) ($claims['payload_hash'] ?? ''), $payloadHash)
            && hash_equals((string) ($claims['idempotency_key'] ?? ''), $idempotencyKey)
            && (int) ($claims['expires_at'] ?? 0) >= $now
            && trim((string) ($claims['nonce'] ?? '')) !== '';
    }

    /** @return array<string,mixed>|null */
    public function claims(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$payload, $signature] = $parts;
        $expected = self::base64UrlEncode(hash_hmac('sha256', $payload, $this->secret, true));
        if (! hash_equals($expected, $signature)) {
            return null;
        }
        $decoded = self::base64UrlDecode($payload);
        if ($decoded === null) {
            return null;
        }
        try {
            $claims = json_decode($decoded, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($claims) && ! array_is_list($claims) ? $claims : null;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}

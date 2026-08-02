<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Evidence;

final class EvidenceChain
{
    public const GENESIS = 'GENESIS';

    public function __construct(
        private string $signingKey,
        private string $keyId = 'primary',
        private ?EvidenceEnvelope $head = null,
    )
    {
        if (strlen($signingKey) < 32) {
            throw new \InvalidArgumentException('Evidence signing key must contain at least 32 bytes.');
        }
    }

    /** @param array<string,mixed> $content @param array<string,mixed> $metadata */
    public function append(string $eventId, string $eventType, array $content, array $metadata = []): EvidenceEnvelope
    {
        $timestamp = gmdate('c');
        $contentHash = hash('sha256', $this->canonicalJson($content));
        $previousHash = $this->head?->contentHash ?? self::GENESIS;
        $body = $this->signingBody($eventId, $eventType, $timestamp, $contentHash, $previousHash, $metadata, $this->keyId);
        $envelope = new EvidenceEnvelope(
            $eventId,
            $eventType,
            $timestamp,
            $content,
            $metadata,
            $contentHash,
            $previousHash,
            hash_hmac('sha256', $body, $this->signingKey),
            $this->keyId,
        );
        $this->head = $envelope;

        return $envelope;
    }

    /** @param list<EvidenceEnvelope> $envelopes */
    public function verify(array $envelopes): bool
    {
        $previous = self::GENESIS;
        foreach ($envelopes as $envelope) {
            if (! $envelope instanceof EvidenceEnvelope || $envelope->previousHash !== $previous) {
                return false;
            }
            $expectedContentHash = hash('sha256', $this->canonicalJson($envelope->content));
            if (! hash_equals($expectedContentHash, $envelope->contentHash)) {
                return false;
            }
            $body = $this->signingBody(
                $envelope->eventId,
                $envelope->eventType,
                $envelope->timestamp,
                $envelope->contentHash,
                $envelope->previousHash,
                $envelope->metadata,
                $envelope->keyId,
            );
            if (! hash_equals(hash_hmac('sha256', $body, $this->signingKey), $envelope->signature)) {
                return false;
            }
            $previous = $envelope->contentHash;
        }

        return true;
    }

    /** @param array<string,mixed> $metadata */
    private function signingBody(string $eventId, string $eventType, string $timestamp, string $contentHash, string $previousHash, array $metadata, string $keyId): string
    {
        return $this->canonicalJson([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'timestamp' => $timestamp,
            'content_hash' => $contentHash,
            'previous_hash' => $previousHash,
            'metadata' => $metadata,
            'key_id' => $keyId,
        ]);
    }

    /** @param mixed $value */
    private function canonicalJson(mixed $value): string
    {
        return json_encode($this->sortValue($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param mixed $value @return mixed */
    private function sortValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortValue($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortValue($item);
        }

        return $value;
    }
}

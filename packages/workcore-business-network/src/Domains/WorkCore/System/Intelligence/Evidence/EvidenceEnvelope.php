<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Evidence;

final readonly class EvidenceEnvelope
{
    /** @param array<string,mixed> $content @param array<string,mixed> $metadata */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public string $timestamp,
        public array $content,
        public array $metadata,
        public string $contentHash,
        public string $previousHash,
        public string $signature,
        public string $keyId = 'primary',
    ) {}

    public function withContentHash(string $contentHash): self
    {
        return new self($this->eventId, $this->eventType, $this->timestamp, $this->content, $this->metadata, $contentHash, $this->previousHash, $this->signature, $this->keyId);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'timestamp' => $this->timestamp,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'content_hash' => $this->contentHash,
            'previous_hash' => $this->previousHash,
            'signature' => $this->signature,
            'key_id' => $this->keyId,
        ];
    }
}

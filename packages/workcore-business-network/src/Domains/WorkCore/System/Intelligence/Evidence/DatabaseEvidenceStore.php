<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Evidence;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

/**
 * Serialises each company evidence stream inside a database transaction.
 */
final class DatabaseEvidenceStore implements EvidenceStoreContract
{
    public function __construct(private ConnectionInterface $db) {}

    public function append(
        int $companyId,
        string $streamKey,
        string $eventId,
        string $eventType,
        array $content,
        array $metadata = [],
    ): EvidenceEnvelope {
        $streamKey = trim($streamKey);
        $eventId = trim($eventId);
        $eventType = trim($eventType);
        if ($companyId < 1 || $streamKey === '' || $eventId === '' || $eventType === '') {
            throw new InvalidArgumentException('Company, stream, event ID and event type are required.');
        }

        return $this->db->transaction(function () use ($companyId, $streamKey, $eventId, $eventType, $content, $metadata): EvidenceEnvelope {
            $head = $this->db->table('tz_ai_evidence')
                ->where('company_id', $companyId)
                ->where('stream_key', $streamKey)
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();

            $chain = new EvidenceChain(
                (string) config('workcore.intelligence.evidence.signing_key', (string) config('app.key', '')),
                (string) config('workcore.intelligence.evidence.key_id', 'primary'),
                $head === null ? null : $this->envelope($head),
            );
            $envelope = $chain->append($eventId, $eventType, $content, $metadata);
            $sequence = $head === null ? 1 : ((int) $head->sequence + 1);

            $this->db->table('tz_ai_evidence')->insert([
                'company_id' => $companyId,
                'stream_key' => $streamKey,
                'sequence' => $sequence,
                'event_id' => $envelope->eventId,
                'event_type' => $envelope->eventType,
                'content_hash' => $envelope->contentHash,
                'previous_hash' => $envelope->previousHash,
                'signature' => $envelope->signature,
                'key_id' => $envelope->keyId,
                'content_json' => json_encode($envelope->content, JSON_THROW_ON_ERROR),
                'metadata_json' => json_encode($envelope->metadata, JSON_THROW_ON_ERROR),
                'created_at' => $envelope->timestamp,
                'updated_at' => $envelope->timestamp,
            ]);

            return $envelope;
        }, 3);
    }

    public function stream(int $companyId, string $streamKey, int $limit = 500): array
    {
        $limit = max(1, min(5000, $limit));
        return $this->db->table('tz_ai_evidence')
            ->where('company_id', $companyId)
            ->where('stream_key', trim($streamKey))
            ->orderBy('sequence')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): EvidenceEnvelope => $this->envelope($row))
            ->all();
    }

    private function envelope(object $row): EvidenceEnvelope
    {
        return new EvidenceEnvelope(
            eventId: (string) $row->event_id,
            eventType: (string) $row->event_type,
            timestamp: (string) $row->created_at,
            content: (array) json_decode((string) $row->content_json, true, 512, JSON_THROW_ON_ERROR),
            metadata: $row->metadata_json === null ? [] : (array) json_decode((string) $row->metadata_json, true, 512, JSON_THROW_ON_ERROR),
            contentHash: (string) $row->content_hash,
            previousHash: (string) $row->previous_hash,
            signature: (string) $row->signature,
            keyId: (string) $row->key_id,
        );
    }
}

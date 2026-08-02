<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Evidence;

interface EvidenceStoreContract
{
    /** @param array<string,mixed> $content @param array<string,mixed> $metadata */
    public function append(
        int $companyId,
        string $streamKey,
        string $eventId,
        string $eventType,
        array $content,
        array $metadata = [],
    ): EvidenceEnvelope;

    /** @return list<EvidenceEnvelope> */
    public function stream(int $companyId, string $streamKey, int $limit = 500): array;
}

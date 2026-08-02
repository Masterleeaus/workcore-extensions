<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

final class KnowledgeRetrievalEvaluator
{
    /** @param list<string> $expectedDocumentIds @return array<string,float|int> */
    public function evaluate(KnowledgeRetrievalResult $retrieval, array $expectedDocumentIds): array
    {
        $expected = array_fill_keys(array_values(array_unique(array_map('strval', $expectedDocumentIds))), true);
        $found = [];
        $firstRelevantRank = null;
        foreach ($retrieval->matches as $index => $match) {
            $documentId = (string) ($match['document_id'] ?? '');
            if ($documentId !== '' && isset($expected[$documentId])) {
                $found[$documentId] = true;
                $firstRelevantRank ??= $index + 1;
            }
        }

        $citationChunkIds = [];
        $stale = 0;
        foreach ($retrieval->citations as $citation) {
            $citationChunkIds[$citation->chunkId] = true;
            $stale += $citation->stale ? 1 : 0;
        }
        $matchedChunks = array_values(array_filter(array_map(static fn (array $match): string => (string) ($match['chunk_id'] ?? ''), $retrieval->matches)));
        $covered = 0;
        foreach ($matchedChunks as $chunkId) {
            $covered += isset($citationChunkIds[$chunkId]) ? 1 : 0;
        }

        return [
            'expected_count' => count($expected),
            'retrieved_count' => count($retrieval->matches),
            'hit_rate' => $expected === [] ? 1.0 : round(count($found) / count($expected), 6),
            'mean_reciprocal_rank' => $firstRelevantRank === null ? 0.0 : round(1 / $firstRelevantRank, 6),
            'citation_coverage' => $matchedChunks === [] ? 1.0 : round($covered / count($matchedChunks), 6),
            'stale_ratio' => $retrieval->citations === [] ? 0.0 : round($stale / count($retrieval->citations), 6),
        ];
    }
}

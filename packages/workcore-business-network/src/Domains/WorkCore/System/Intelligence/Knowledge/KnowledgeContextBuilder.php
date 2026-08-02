<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

use InvalidArgumentException;

final class KnowledgeContextBuilder
{
    public function build(KnowledgeRetrievalResult $retrieval, int $maxTokens = 4000): KnowledgeContextWindow
    {
        if ($maxTokens < 16 || $maxTokens > 100000) {
            throw new InvalidArgumentException('Knowledge context token limit must be between 16 and 100000.');
        }

        $citationByChunk = [];
        foreach ($retrieval->citations as $citation) {
            $citationByChunk[$citation->chunkId] = $citation;
        }

        $parts = [];
        $citations = [];
        $usedTokens = 0;
        $truncated = false;
        $number = 1;
        foreach ($retrieval->matches as $match) {
            $content = trim((string) ($match['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $prefix = '[' . $number . '] ';
            $available = $maxTokens - $usedTokens;
            $estimated = $this->estimateTokens($prefix . $content);
            if ($available <= 0) {
                $truncated = true;
                break;
            }

            if ($estimated > $available) {
                $maxChars = max(0, ($available * 4) - strlen($prefix) - 3);
                if ($maxChars < 16) {
                    $truncated = true;
                    break;
                }
                $content = substr($content, 0, $maxChars) . '...';
                $estimated = $this->estimateTokens($prefix . $content);
                $truncated = true;
            }

            $parts[] = $prefix . $content;
            $usedTokens += $estimated;
            $chunkId = (string) ($match['chunk_id'] ?? '');
            if (isset($citationByChunk[$chunkId])) {
                $citation = $citationByChunk[$chunkId]->toArray();
                $citation['number'] = $number;
                $citations[] = $citation;
            }
            $number++;

            if ($usedTokens >= $maxTokens) {
                $truncated = true;
                break;
            }
        }

        if (count($parts) < count($retrieval->matches)) {
            $truncated = true;
        }

        return new KnowledgeContextWindow(implode("\n\n", $parts), $citations, $usedTokens, $truncated);
    }

    private function estimateTokens(string $content): int
    {
        return max(1, (int) ceil(strlen($content) / 4));
    }
}

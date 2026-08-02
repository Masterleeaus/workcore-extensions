<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

use InvalidArgumentException;

final class CharacterChunker
{
    public function __construct(private int $chunkSize = 1200, private int $overlap = 150)
    {
        if ($chunkSize < 20 || $overlap < 0 || $overlap >= $chunkSize) {
            throw new InvalidArgumentException('Chunk size must exceed overlap and be at least 20 characters.');
        }
    }

    /** @param array<string,mixed> $metadata @return list<KnowledgeChunk> */
    public function chunk(string $content, array $metadata = []): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $chunks = [];
        $length = strlen($content);
        $start = 0;
        $sequence = 0;
        while ($start < $length) {
            $hardEnd = min($length, $start + $this->chunkSize);
            $end = $hardEnd;
            if ($hardEnd < $length) {
                $window = substr($content, $start, $hardEnd - $start);
                $break = max(strrpos($window, "\n") ?: 0, strrpos($window, ' ') ?: 0);
                if ($break >= (int) floor($this->chunkSize * 0.6)) {
                    $end = $start + $break;
                }
            }
            if ($end <= $start) {
                $end = $hardEnd;
            }
            $chunk = trim(substr($content, $start, $end - $start));
            if ($chunk !== '') {
                $chunks[] = new KnowledgeChunk($chunk, $start, $end, $sequence++, $metadata);
            }
            if ($end >= $length) {
                break;
            }
            $start = max($start + 1, $end - $this->overlap);
        }

        return $chunks;
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

final class ReciprocalRankFusion
{
    public function __construct(private int $k = 60) {}

    /**
     * @param list<array<string,mixed>> $semanticResults
     * @param list<array<string,mixed>> $lexicalResults
     * @return list<array<string,mixed>>
     */
    public function merge(array $semanticResults, array $lexicalResults, float $semanticWeight = 0.7, float $lexicalWeight = 0.3, int $limit = 10): array
    {
        $scores = [];
        $items = [];
        foreach ($semanticResults as $rank => $result) {
            $id = (string) ($result['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $scores[$id] = ($scores[$id] ?? 0.0) + $semanticWeight / ($this->k + $rank + 1);
            $items[$id] = $result;
        }
        foreach ($lexicalResults as $rank => $result) {
            $id = (string) ($result['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $scores[$id] = ($scores[$id] ?? 0.0) + $lexicalWeight / ($this->k + $rank + 1);
            $items[$id] ??= $result;
        }
        arsort($scores);
        $merged = [];
        foreach (array_slice(array_keys($scores), 0, max(1, $limit)) as $id) {
            $merged[] = [...$items[$id], 'score' => $scores[$id]];
        }

        return $merged;
    }
}

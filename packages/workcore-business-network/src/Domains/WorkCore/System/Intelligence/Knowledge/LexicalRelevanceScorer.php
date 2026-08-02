<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

final class LexicalRelevanceScorer
{
    /** @var list<string> */
    private array $stopWords = ['a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'in', 'is', 'it', 'of', 'on', 'or', 'that', 'the', 'to', 'was', 'with'];

    public function score(string $query, string $content): float
    {
        $queryTokens = $this->tokens($query);
        if ($queryTokens === []) {
            return 0.0;
        }

        $contentTokens = $this->tokens($content);
        if ($contentTokens === []) {
            return 0.0;
        }

        $contentSet = array_fill_keys($contentTokens, true);
        $hits = 0;
        foreach ($queryTokens as $token) {
            $hits += isset($contentSet[$token]) ? 1 : 0;
        }

        $coverage = $hits / count($queryTokens);
        $phraseBonus = str_contains($this->normalise($content), $this->normalise($query)) ? 0.25 : 0.0;

        return min(1.0, round(($coverage * 0.75) + $phraseBonus, 6));
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        $tokens = preg_split('/[^a-z0-9]+/', $this->normalise($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($tokens, fn (string $token): bool => strlen($token) > 1 && ! in_array($token, $this->stopWords, true))));
    }

    private function normalise(string $value): string
    {
        return strtolower(trim($value));
    }
}

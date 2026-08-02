<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Learning;

final class CapabilityMatchRanker
{
    public function __construct(private float $reuseThreshold = 0.78, private float $adaptThreshold = 0.42) {}

    /** @param list<array<string,mixed>> $catalogue */
    public function decide(string $requirement, array $catalogue): CapabilityDecision
    {
        $requirementTokens = $this->tokens($requirement);
        $scored = [];
        foreach ($catalogue as $candidate) {
            $key = trim((string) ($candidate['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $search = implode(' ', [
                $key,
                (string) ($candidate['label'] ?? ''),
                implode(' ', array_map('strval', (array) ($candidate['terms'] ?? []))),
            ]);
            $candidateTokens = $this->tokens($search);
            $intersection = array_values(array_unique(array_intersect($requirementTokens, $candidateTokens)));
            $coverage = $requirementTokens === [] ? 0.0 : count($intersection) / count($requirementTokens);
            $precision = $candidateTokens === [] ? 0.0 : count($intersection) / count($candidateTokens);
            $phraseBonus = str_contains($this->normalise($search), $this->normalise($requirement)) ? 0.15 : 0.0;
            $score = min(1.0, ($coverage * 0.78) + ($precision * 0.22) + $phraseBonus);
            $scored[] = ['key' => $key, 'score' => $score, 'coverage' => $coverage, 'matched_terms' => $intersection];
        }
        usort($scored, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);
        $best = $scored[0] ?? ['key' => null, 'score' => 0.0];
        $decision = $best['score'] >= $this->reuseThreshold
            ? 'reuse'
            : ($best['score'] >= $this->adaptThreshold ? 'adapt' : 'create');

        return new CapabilityDecision($decision, $decision === 'create' ? null : $best['key'], (float) $best['score'], array_slice($scored, 0, 8));
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        $parts = preg_split('/[^a-z0-9]+/', $this->normalise($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = ['a', 'an', 'and', 'the', 'to', 'for', 'of', 'with', 'in', 'on', 'by'];
        $tokens = [];
        foreach ($parts as $part) {
            if (in_array($part, $stop, true)) {
                continue;
            }
            foreach (['ing', 'ers', 'ies', 'ed', 'es', 's'] as $suffix) {
                if (strlen($part) > strlen($suffix) + 3 && str_ends_with($part, $suffix)) {
                    $part = substr($part, 0, -strlen($suffix));
                    break;
                }
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    private function normalise(string $value): string
    {
        return strtolower(trim($value));
    }
}

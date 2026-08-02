<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

use App\Domains\WorkCore\System\Expansion\Data\CapabilityInventory;
use App\Domains\WorkCore\System\Expansion\Data\ExpansionAssessment;
use InvalidArgumentException;

final class CapabilityGapAnalyzer
{
    public function __construct(
        private RequirementNormalizer $normalizer,
        private float $matchThreshold = 0.38,
    ) {
        if ($matchThreshold <= 0 || $matchThreshold > 1) {
            throw new InvalidArgumentException('Capability match threshold must be greater than zero and at most one.');
        }
    }

    public function assess(string $requirement, CapabilityInventory $inventory): ExpansionAssessment
    {
        $requirementTokens = $this->normalizer->tokens($requirement);
        if ($requirementTokens === []) {
            throw new InvalidArgumentException('A meaningful capability requirement is required.');
        }

        $scored = [];
        foreach ($this->candidates($inventory) as $candidate) {
            $metrics = $this->score($requirementTokens, $this->normalizer->tokens($candidate['search_text']));
            $scored[] = [
                'key' => $candidate['key'],
                'target_key' => $candidate['target_key'],
                'type' => $candidate['type'],
                'granularity' => $candidate['granularity'],
                'score' => round($metrics['score'], 4),
                'coverage' => round($metrics['coverage'], 4),
                'overlap' => $metrics['overlap'],
            ];
        }

        usort($scored, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);
        $features = array_values(array_filter($scored, static fn (array $candidate): bool => $candidate['granularity'] === 'feature'));
        $domains = array_values(array_filter($scored, static fn (array $candidate): bool => $candidate['granularity'] === 'domain'));
        $bestFeature = $features[0] ?? null;
        $bestDomain = $domains[0] ?? null;
        $alternatives = array_slice($scored, 0, 8);
        $normalised = $this->normalizer->normalise($requirement);

        if ($bestFeature !== null && (float) $bestFeature['score'] >= $this->matchThreshold) {
            return new ExpansionAssessment(
                status: 'existing',
                normalisedRequirement: $normalised,
                matchedKey: (string) $bestFeature['target_key'],
                score: (float) $bestFeature['score'],
                alternatives: $alternatives,
                matchType: (string) $bestFeature['type'],
            );
        }

        if ($bestDomain !== null && $this->domainIdentityIsComplete($bestDomain)) {
            return new ExpansionAssessment(
                status: 'existing',
                normalisedRequirement: $normalised,
                matchedKey: (string) $bestDomain['target_key'],
                score: (float) $bestDomain['score'],
                alternatives: $alternatives,
                matchType: (string) $bestDomain['type'],
            );
        }

        if ($bestDomain !== null && (int) $bestDomain['overlap'] > 0) {
            return new ExpansionAssessment(
                status: 'extend_existing',
                normalisedRequirement: $normalised,
                matchedKey: (string) $bestDomain['target_key'],
                score: (float) $bestDomain['score'],
                alternatives: $alternatives,
                matchType: (string) $bestDomain['type'],
            );
        }

        $best = $scored[0] ?? ['score' => 0.0];
        return new ExpansionAssessment(
            status: 'missing',
            normalisedRequirement: $normalised,
            matchedKey: null,
            score: (float) $best['score'],
            alternatives: $alternatives,
        );
    }

    /** @param array<string,mixed> $candidate */
    private function domainIdentityIsComplete(array $candidate): bool
    {
        return (float) $candidate['coverage'] >= 0.85 || (float) $candidate['score'] >= 0.78;
    }

    /** @return array<int,array{key:string,target_key:string,type:string,granularity:string,search_text:string}> */
    private function candidates(CapabilityInventory $inventory): array
    {
        $candidates = [];

        foreach ($inventory->capabilities as $key => $definition) {
            $key = (string) $key;
            $candidates[] = [
                'key' => $key,
                'target_key' => $key,
                'type' => 'capability',
                'granularity' => 'domain',
                'search_text' => $key,
            ];

            $metadata = (array) ($definition['metadata'] ?? []);
            if ($metadata !== []) {
                $candidates[] = [
                    'key' => $key . ':metadata',
                    'target_key' => $key,
                    'type' => 'capability_metadata',
                    'granularity' => 'feature',
                    'search_text' => $key . ' ' . (json_encode($metadata) ?: ''),
                ];
            }
        }

        foreach ($inventory->modules as $key => $provider) {
            $target = isset($inventory->capabilities['workcore.' . $key]) ? 'workcore.' . $key : (string) $key;
            $candidates[] = [
                'key' => 'module:' . $key,
                'target_key' => $target,
                'type' => 'module',
                'granularity' => 'domain',
                'search_text' => (string) $key . ' ' . (string) $provider,
            ];
        }

        foreach ($inventory->actions as $key => $definition) {
            $target = trim((string) ($definition['capability'] ?? ''));
            if ($target === '') {
                $target = (string) $key;
            }
            $candidates[] = [
                'key' => (string) $key,
                'target_key' => $target,
                'type' => 'action',
                'granularity' => 'feature',
                'search_text' => (string) $key . ' ' . (json_encode($definition['metadata'] ?? []) ?: ''),
            ];
        }

        foreach ($inventory->readModels as $key => $definition) {
            $target = trim((string) ($definition['capability'] ?? ''));
            if ($target === '') {
                $target = (string) $key;
            }
            $candidates[] = [
                'key' => (string) $key,
                'target_key' => $target,
                'type' => 'read_model',
                'granularity' => 'feature',
                'search_text' => (string) $key . ' ' . (json_encode($definition['metadata'] ?? []) ?: ''),
            ];
        }

        foreach ($inventory->adaptiveDomains as $domain) {
            $target = (string) ($domain['capability_key'] ?? $domain['slug'] ?? '');
            if ($target === '') {
                continue;
            }
            $identity = implode(' ', [
                $target,
                (string) ($domain['slug'] ?? ''),
                (string) ($domain['name'] ?? ''),
                (string) ($domain['description'] ?? ''),
            ]);
            $candidates[] = [
                'key' => $target,
                'target_key' => $target,
                'type' => 'adaptive_domain',
                'granularity' => 'domain',
                'search_text' => $identity,
            ];

            foreach ((array) ($domain['blueprint']['fields'] ?? []) as $field) {
                if (! is_array($field)) {
                    continue;
                }
                $fieldName = trim((string) ($field['name'] ?? ''));
                if ($fieldName === '') {
                    continue;
                }
                $candidates[] = [
                    'key' => $target . ':field:' . $fieldName,
                    'target_key' => $target,
                    'type' => 'adaptive_field',
                    'granularity' => 'feature',
                    'search_text' => implode(' ', [
                        $fieldName,
                        (string) ($field['label'] ?? ''),
                        (string) ($field['description'] ?? ''),
                        (string) ($field['type'] ?? ''),
                    ]),
                ];
            }
        }

        foreach ($inventory->adaptiveFeatures as $feature) {
            $target = trim((string) ($feature['capability_key'] ?? $feature['slug'] ?? ''));
            if ($target === '') {
                continue;
            }
            $blueprint = (array) ($feature['blueprint'] ?? []);
            $candidates[] = [
                'key' => $target,
                'target_key' => $target,
                'type' => 'adaptive_feature',
                'granularity' => 'feature',
                'search_text' => implode(' ', [
                    $target,
                    (string) ($feature['slug'] ?? ''),
                    (string) ($feature['name'] ?? ''),
                    (string) ($feature['description'] ?? ''),
                    (string) ($blueprint['trigger']['event'] ?? ''),
                    json_encode($blueprint['conditions'] ?? []) ?: '',
                    json_encode($blueprint['steps'] ?? []) ?: '',
                ]),
            ];
        }

        foreach ($inventory->recordSchemas as $schema) {
            $target = trim((string) ($schema['capability_key'] ?? ''));
            $table = trim((string) ($schema['table'] ?? ''));
            if ($target === '' || $table === '') {
                continue;
            }

            foreach ((array) ($schema['columns'] ?? []) as $column) {
                $column = trim((string) $column);
                if ($column === '' || in_array($column, ['id', 'public_id', 'company_id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                    continue;
                }
                $candidates[] = [
                    'key' => $target . ':schema:' . $table . ':' . $column,
                    'target_key' => $target,
                    'type' => 'record_schema',
                    'granularity' => 'feature',
                    'search_text' => $target . ' ' . $table . ' ' . $column,
                ];
            }
        }

        foreach ($inventory->sourceFeatures as $feature) {
            $key = trim((string) ($feature['feature'] ?? ''));
            if ($key === '') {
                continue;
            }
            $canonicalDomain = trim((string) ($feature['canonical_domain'] ?? ''));
            $target = $canonicalDomain !== ''
                ? (str_starts_with($canonicalDomain, 'workcore.') ? $canonicalDomain : 'workcore.' . $canonicalDomain)
                : $key;
            $candidates[] = [
                'key' => $key,
                'target_key' => $target,
                'type' => 'source_feature',
                'granularity' => 'feature',
                'search_text' => json_encode($feature) ?: $key,
            ];
        }

        return $candidates;
    }

    /** @param array<int,string> $requirementTokens @param array<int,string> $candidateTokens @return array{score:float,coverage:float,overlap:int} */
    private function score(array $requirementTokens, array $candidateTokens): array
    {
        if ($candidateTokens === []) {
            return ['score' => 0.0, 'coverage' => 0.0, 'overlap' => 0];
        }

        $intersection = array_values(array_unique(array_intersect($requirementTokens, $candidateTokens)));
        if ($intersection === []) {
            return ['score' => 0.0, 'coverage' => 0.0, 'overlap' => 0];
        }

        $coverage = count($intersection) / count($requirementTokens);
        $precision = count($intersection) / count($candidateTokens);
        $exactBonus = count($intersection) >= 2 ? 0.08 : 0.0;

        return [
            'score' => min(1.0, ($coverage * 0.75) + ($precision * 0.25) + $exactBonus),
            'coverage' => $coverage,
            'overlap' => count($intersection),
        ];
    }
}

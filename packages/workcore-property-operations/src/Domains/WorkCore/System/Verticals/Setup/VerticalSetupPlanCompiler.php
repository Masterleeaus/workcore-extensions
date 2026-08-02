<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Setup;

use App\Domains\WorkCore\System\Verticals\Operations\OperationalBlueprintCompiler;
use App\Domains\WorkCore\System\Verticals\VerticalRegistry;
use InvalidArgumentException;

final class VerticalSetupPlanCompiler
{
    /** @param array<string,mixed> $config */
    public function __construct(
        private VerticalRegistry $registry,
        private VerticalSetupQuestionBank $questions,
        private SetupAnswerValidator $validator,
        private array $config = [],
        private ?OperationalBlueprintCompiler $operations = null,
    ) {}

    /** @param list<string> $addOns @param array<string,mixed> $answers */
    public function compile(string $primaryVertical, array $addOns = [], array $answers = []): VerticalSetupPlan
    {
        $primary = $this->registry->get($primaryVertical);
        if ($primary->type === 'foundation') {
            throw new InvalidArgumentException("Foundation pack [{$primaryVertical}] cannot be selected directly.");
        }
        $addOns = array_values(array_unique(array_filter(array_map('strval', $addOns), static fn (string $key): bool => $key !== '' && $key !== $primaryVertical)));
        foreach ($addOns as $key) {
            if ($this->registry->get($key)->type === 'foundation') {
                throw new InvalidArgumentException("Foundation pack [{$key}] cannot be selected directly.");
            }
        }
        $definitions = $this->questions->definitions($primaryVertical, $addOns, $answers);
        $normalised = $this->validator->validate($definitions, $answers);
        $businessLines = $this->businessLines($primaryVertical, $addOns, $normalised);
        $locales = $this->locales($normalised);
        $features = [];
        $terminology = [];
        $settings = [];
        foreach ($normalised as $key => $value) {
            if (str_starts_with($key, 'feature.')) {
                $features[substr($key, 8)] = (bool) $value;
            } elseif (str_starts_with($key, 'terminology.')) {
                $terminology[substr($key, 12)] = (string) $value;
            } elseif (! in_array($key, ['business.lines', 'documents.enabled'], true)) {
                $settings[$key] = $value;
            }
        }
        ksort($features); ksort($terminology); ksort($settings);
        return new VerticalSetupPlan(
            primaryVertical: $primaryVertical,
            addOns: $addOns,
            businessLines: $businessLines,
            locales: $locales,
            features: $features,
            terminology: $terminology,
            settings: $settings,
            documents: $this->documents($primaryVertical, $addOns, $normalised),
            operations: $this->operations?->compile($primaryVertical, $addOns, $normalised)->toArray() ?? [],
            metadata: [
                'pack_versions' => array_combine(array_merge([$primaryVertical], $addOns), array_map(fn (string $key): string => $this->registry->get($key)->version, array_merge([$primaryVertical], $addOns))),
                'requires_human_review' => true,
            ],
        );
    }

    /** @param list<string> $addOns @param array<string,mixed> $answers @return list<array<string,mixed>> */
    private function businessLines(string $primaryVertical, array $addOns, array $answers): array
    {
        $explicit = (array) ($answers['business.lines'] ?? []);
        $lines = [];
        if ($explicit !== []) {
            foreach ($explicit as $line) {
                $verticalKey = (string) ($line['vertical_key'] ?? $primaryVertical);
                if ($verticalKey !== $primaryVertical && ! in_array($verticalKey, $addOns, true)) {
                    throw new InvalidArgumentException("Business line references unselected vertical [{$verticalKey}].");
                }
                $lines[] = [
                    'key' => $this->normaliseKey((string) $line['key']), 'name' => trim((string) $line['name']),
                    'vertical_key' => $verticalKey, 'status' => 'active', 'settings' => (array) ($line['settings'] ?? []),
                ];
            }
        } else {
            foreach (array_merge([$primaryVertical], $addOns) as $packKey) {
                foreach ((array) ($this->registry->get($packKey)->setup['default_business_lines'] ?? []) as $line) {
                    if (! is_array($line)) { continue; }
                    $key = $this->normaliseKey((string) ($line['key'] ?? ''));
                    $name = trim((string) ($line['name'] ?? ''));
                    if ($key !== '' && $name !== '') {
                        $lines[] = ['key' => $key, 'name' => $name, 'vertical_key' => $packKey, 'status' => 'active', 'settings' => []];
                    }
                }
            }
        }
        if ($lines === []) {
            $lines[] = ['key' => $primaryVertical, 'name' => $this->registry->get($primaryVertical)->label, 'vertical_key' => $primaryVertical, 'status' => 'active', 'settings' => []];
        }
        $seen = [];
        foreach ($lines as $index => &$line) {
            if (isset($seen[$line['key']])) {
                throw new InvalidArgumentException("Duplicate business-line key [{$line['key']}].");
            }
            $seen[$line['key']] = true;
            $line['is_default'] = $index === 0 && $line['vertical_key'] === $primaryVertical;
        }
        unset($line);
        return $lines;
    }

    /** @param array<string,mixed> $answers @return list<array<string,mixed>> */
    private function locales(array $answers): array
    {
        $default = trim((string) ($answers['language.default_locale'] ?? 'en-AU')) ?: 'en-AU';
        $codes = array_values(array_unique(array_merge([$default], array_map('strval', (array) ($answers['language.additional_locales'] ?? [])))));
        $locales = [];
        foreach ($codes as $code) {
            $code = trim($code);
            if ($code === '') { continue; }
            $language = strtolower(explode('-', $code)[0]);
            $locales[] = [
                'locale' => $code, 'display_name' => $code,
                'direction' => in_array($language, ['ar', 'fa', 'he', 'ur'], true) ? 'rtl' : 'ltr',
                'is_default' => $code === $default, 'is_active' => true, 'source' => 'vertical_setup',
            ];
        }
        return $locales;
    }

    /** @param list<string> $addOns @param array<string,mixed> $answers @return list<array<string,mixed>> */
    private function documents(string $primaryVertical, array $addOns, array $answers): array
    {
        $library = (array) ($this->config['document_library'] ?? []);
        $selected = (array) ($answers['documents.enabled'] ?? []);
        if ($selected === []) {
            $selected = $this->questions->availableDocumentKeys($primaryVertical, $addOns);
        }
        $documents = [];
        foreach (array_values(array_unique(array_map('strval', $selected))) as $key) {
            if (! isset($library[$key]) || ! is_array($library[$key])) { continue; }
            $template = $library[$key];
            $documents[] = [
                'document_key' => $key, 'document_type' => (string) ($template['type'] ?? 'document'),
                'title' => (string) ($template['title'] ?? $key),
                'locale' => (string) ($answers['language.default_locale'] ?? 'en-AU'),
                'content' => (string) ($template['content'] ?? ''),
                'metadata' => ['generated_by' => 'vertical_setup', 'brand_voice' => $answers['documents.brand_voice'] ?? 'professional', 'requires_human_review' => true],
            ];
        }
        return $documents;
    }

    private function normaliseKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '_', $value);
        return trim($value, '_');
    }
}

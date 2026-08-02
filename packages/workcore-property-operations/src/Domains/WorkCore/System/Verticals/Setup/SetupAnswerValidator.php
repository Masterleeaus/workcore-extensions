<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Setup;

use InvalidArgumentException;

final class SetupAnswerValidator
{
    /** @param list<SetupQuestion> $questions @param array<string,mixed> $answers @return array<string,mixed> */
    public function validate(array $questions, array $answers): array
    {
        $normalised = [];
        $known = [];
        foreach ($questions as $question) {
            $known[$question->key] = true;
            $has = array_key_exists($question->key, $answers);
            $value = $has ? $answers[$question->key] : $question->default;
            if ($question->required && $this->empty($value)) {
                throw new InvalidArgumentException("Required setup answer [{$question->key}] is missing.");
            }
            if (! $has && $value === null) {
                continue;
            }
            $normalised[$question->key] = $this->normalise($question, $value);
        }
        foreach ($answers as $key => $value) {
            $key = (string) $key;
            if (isset($known[$key])) {
                continue;
            }
            if (! str_starts_with($key, 'terminology.') && ! str_starts_with($key, 'feature.') && ! str_starts_with($key, 'setting.')) {
                throw new InvalidArgumentException("Unknown setup answer [{$key}].");
            }
            $normalised[$key] = $value;
        }
        ksort($normalised);
        return $normalised;
    }

    private function normalise(SetupQuestion $question, mixed $value): mixed
    {
        if (in_array($question->type, ['text', 'textarea'], true)) {
            if (! is_scalar($value)) {
                throw new InvalidArgumentException("Answer [{$question->key}] must be text.");
            }
            return trim((string) $value);
        }
        if ($question->type === 'boolean') {
            if (is_bool($value)) {
                return $value;
            }
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed === null) {
                throw new InvalidArgumentException("Answer [{$question->key}] must be boolean.");
            }
            return $parsed;
        }
        if ($question->type === 'integer') {
            if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                throw new InvalidArgumentException("Answer [{$question->key}] must be an integer.");
            }
            return (int) $value;
        }
        if ($question->type === 'select') {
            $value = (string) $value;
            if ($question->options !== [] && ! in_array($value, $question->options, true)) {
                throw new InvalidArgumentException("Answer [{$question->key}] contains an unsupported option [{$value}].");
            }
            return $value;
        }
        if (in_array($question->type, ['multiselect', 'list', 'document_multiselect'], true)) {
            if (! is_array($value)) {
                throw new InvalidArgumentException("Answer [{$question->key}] must be a list.");
            }
            $values = array_values(array_unique(array_map(static fn (mixed $item): string => trim((string) $item), $value)));
            $values = array_values(array_filter($values, static fn (string $item): bool => $item !== ''));
            if ($question->options !== []) {
                foreach ($values as $item) {
                    if (! in_array($item, $question->options, true)) {
                        throw new InvalidArgumentException("Answer [{$question->key}] contains an unsupported option [{$item}].");
                    }
                }
            }
            return $values;
        }
        if ($question->type === 'business_lines') {
            if (! is_array($value)) {
                throw new InvalidArgumentException("Answer [{$question->key}] must be a business-line list.");
            }
            $lines = [];
            foreach ($value as $line) {
                if (! is_array($line) || trim((string) ($line['key'] ?? '')) === '' || trim((string) ($line['name'] ?? '')) === '') {
                    throw new InvalidArgumentException("Answer [{$question->key}] contains an invalid business line.");
                }
                $lines[] = $line;
            }
            return $lines;
        }
        if ($question->type === 'locale_map') {
            if (! is_array($value)) {
                throw new InvalidArgumentException("Answer [{$question->key}] must be a locale map.");
            }
            return $value;
        }
        return $value;
    }

    private function empty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}

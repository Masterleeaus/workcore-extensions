<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Proposals;

final class StrictSchemaValidator
{
    public function __construct(
        private int $maxDepth = 20,
        private int $maxArrayItems = 100,
        private int $maxStringLength = 10000,
    ) {}

    /** @param mixed $value @param array<string,mixed> $schema @return list<string> */
    public function validate(mixed $value, array $schema): array
    {
        $errors = [];
        $this->validateAt($value, $schema, '$', 0, $errors);

        return $errors;
    }

    /** @param mixed $value @param array<string,mixed> $schema @param list<string> $errors */
    private function validateAt(mixed $value, array $schema, string $path, int $depth, array &$errors): void
    {
        if ($depth > $this->maxDepth) {
            $errors[] = "{$path}: maximum nesting depth exceeded";
            return;
        }

        $type = $schema['type'] ?? null;
        if (is_array($type)) {
            $allowedTypes = array_values(array_map('strval', $type));
            $matchedType = null;
            foreach ($allowedTypes as $candidate) {
                if ($this->matchesType($value, $candidate)) {
                    $matchedType = $candidate;
                    break;
                }
            }
            if ($matchedType === null) {
                $errors[] = "{$path}: expected one of " . implode(', ', $allowedTypes);
                return;
            }
            $type = $matchedType;
        }
        if (is_string($type) && ! $this->matchesType($value, $type)) {
            $errors[] = "{$path}: expected {$type}";
            return;
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $errors[] = "{$path}: value is not in the allowed enum";
            return;
        }

        if ($type === 'object' && is_array($value)) {
            $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
            foreach ((array) ($schema['required'] ?? []) as $required) {
                if (! array_key_exists((string) $required, $value)) {
                    $errors[] = "{$path}.{$required}: required property is missing";
                }
            }
            if (($schema['additionalProperties'] ?? false) === false) {
                foreach (array_keys($value) as $key) {
                    if (! array_key_exists((string) $key, $properties)) {
                        $errors[] = "{$path}.{$key}: unknown property";
                    }
                }
            }
            foreach ($properties as $key => $childSchema) {
                if (array_key_exists((string) $key, $value) && is_array($childSchema)) {
                    $this->validateAt($value[$key], $childSchema, "{$path}.{$key}", $depth + 1, $errors);
                }
            }
            return;
        }

        if ($type === 'array' && is_array($value)) {
            if (count($value) > min($this->maxArrayItems, (int) ($schema['maxItems'] ?? $this->maxArrayItems))) {
                $errors[] = "{$path}: too many array items";
            }
            if (isset($schema['minItems']) && count($value) < (int) $schema['minItems']) {
                $errors[] = "{$path}: too few array items";
            }
            $itemSchema = is_array($schema['items'] ?? null) ? $schema['items'] : [];
            foreach ($value as $index => $item) {
                $this->validateAt($item, $itemSchema, "{$path}[{$index}]", $depth + 1, $errors);
            }
            return;
        }

        if ($type === 'string' && is_string($value)) {
            $length = strlen($value);
            $maximum = min($this->maxStringLength, (int) ($schema['maxLength'] ?? $this->maxStringLength));
            if ($length > $maximum) {
                $errors[] = "{$path}: string exceeds maximum length";
            }
            if (isset($schema['minLength']) && $length < (int) $schema['minLength']) {
                $errors[] = "{$path}: string is shorter than minimum length";
            }
            if (isset($schema['pattern']) && @preg_match('/' . str_replace('/', '\\/', (string) $schema['pattern']) . '/', $value) !== 1) {
                $errors[] = "{$path}: string does not match required pattern";
            }
            return;
        }

        if (($type === 'integer' || $type === 'number') && (is_int($value) || is_float($value))) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                $errors[] = "{$path}: value is below minimum";
            }
            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                $errors[] = "{$path}: value exceeds maximum";
            }
        }
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'object' => is_array($value) && ! array_is_list($value),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => false,
        };
    }
}

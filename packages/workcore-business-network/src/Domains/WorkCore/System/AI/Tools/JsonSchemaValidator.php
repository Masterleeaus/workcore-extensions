<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Tools;

final class JsonSchemaValidator
{
    /** @param array<string,mixed> $value @param array<string,mixed> $schema @return list<string> */
    public function validate(array $value, array $schema): array
    {
        $errors = [];
        $this->validateValue($value, $schema, '$', $errors);
        return $errors;
    }

    /** @param list<string> $errors @param array<string,mixed> $schema */
    private function validateValue(mixed $value, array $schema, string $path, array &$errors): void
    {
        $type = $schema['type'] ?? null;
        if (is_string($type) && ! $this->matchesType($value, $type)) {
            $errors[] = "{$path} must be of type {$type}.";
            return;
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $errors[] = "{$path} must be one of the allowed values.";
        }

        if (is_string($value)) {
            if (isset($schema['minLength']) && mb_strlen($value) < (int) $schema['minLength']) {
                $errors[] = "{$path} is shorter than the minimum length.";
            }
            if (isset($schema['maxLength']) && mb_strlen($value) > (int) $schema['maxLength']) {
                $errors[] = "{$path} exceeds the maximum length.";
            }
            if (isset($schema['pattern']) && is_string($schema['pattern']) && @preg_match($schema['pattern'], $value) !== 1) {
                $errors[] = "{$path} does not match the required pattern.";
            }
        }

        if (is_int($value) || is_float($value)) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                $errors[] = "{$path} is below the minimum.";
            }
            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                $errors[] = "{$path} exceeds the maximum.";
            }
        }

        if (($schema['type'] ?? null) === 'object' && is_array($value)) {
            $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
            foreach ((array) ($schema['required'] ?? []) as $required) {
                if (! array_key_exists((string) $required, $value)) {
                    $errors[] = "{$path}.{$required} is required.";
                }
            }
            if (($schema['additionalProperties'] ?? true) === false) {
                foreach (array_keys($value) as $key) {
                    if (! array_key_exists((string) $key, $properties)) {
                        $errors[] = "{$path}.{$key} is not allowed.";
                    }
                }
            }
            foreach ($value as $key => $item) {
                if (isset($properties[$key]) && is_array($properties[$key])) {
                    $this->validateValue($item, $properties[$key], "{$path}.{$key}", $errors);
                }
            }
        }

        if (($schema['type'] ?? null) === 'array' && is_array($value)) {
            if (isset($schema['minItems']) && count($value) < (int) $schema['minItems']) {
                $errors[] = "{$path} has fewer than the minimum number of items.";
            }
            if (isset($schema['maxItems']) && count($value) > (int) $schema['maxItems']) {
                $errors[] = "{$path} exceeds the maximum number of items.";
            }
            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($value as $index => $item) {
                    $this->validateValue($item, $schema['items'], "{$path}[{$index}]", $errors);
                }
            }
        }
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            'array' => is_array($value) && ($value === [] || array_is_list($value)),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => false,
        };
    }
}

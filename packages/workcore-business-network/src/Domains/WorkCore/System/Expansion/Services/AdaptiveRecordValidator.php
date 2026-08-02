<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

use App\Domains\WorkCore\System\Expansion\Contracts\AdaptiveReferenceValidatorContract;
use DateTimeImmutable;
use InvalidArgumentException;

final class AdaptiveRecordValidator
{
    public function __construct(
        private AdaptiveDomainBlueprintValidator $blueprints,
        private ?AdaptiveReferenceValidatorContract $references = null,
    ) {}

    /** @param array<string,mixed> $blueprint @param array<string,mixed> $data @return array<string,mixed> */
    public function validate(array $blueprint, array $data, ?int $companyId = null): array
    {
        $blueprint = $this->blueprints->validate($blueprint);
        $fields = [];
        foreach ($blueprint['fields'] as $field) {
            $fields[$field['name']] = $field;
        }

        foreach ($data as $key => $_value) {
            if (! isset($fields[$key])) {
                throw new InvalidArgumentException("Adaptive record key [{$key}] is not defined by the domain blueprint.");
            }
        }

        $validated = [];
        foreach ($fields as $name => $field) {
            $present = array_key_exists($name, $data);
            if (! $present) {
                if (($field['required'] ?? false) && ! array_key_exists('default', $field)) {
                    throw new InvalidArgumentException("Adaptive record field [{$name}] is required.");
                }
                if (array_key_exists('default', $field)) {
                    $validated[$name] = $this->validateValue($field, $field['default'], $companyId);
                }
                continue;
            }

            $value = $data[$name];
            if ($value === null && ! ($field['required'] ?? false)) {
                $validated[$name] = null;
                continue;
            }
            $validated[$name] = $this->validateValue($field, $value, $companyId);
        }

        return $validated;
    }

    /** @param array<string,mixed> $field */
    private function validateValue(array $field, mixed $value, ?int $companyId): mixed
    {
        $name = (string) $field['name'];
        $type = (string) $field['type'];

        return match ($type) {
            'text', 'textarea', 'email', 'phone', 'url' => $this->stringValue($name, $type, $value),
            'reference' => $this->referenceValue($field, $value, $companyId),
            'integer' => $this->integerValue($name, $value),
            'number', 'money' => $this->numberValue($name, $value),
            'boolean' => $this->booleanValue($name, $value),
            'date' => $this->dateValue($name, $value, 'Y-m-d'),
            'datetime' => $this->dateTimeValue($name, $value),
            'select' => $this->selectValue($name, $value, (array) ($field['options'] ?? [])),
            'multiselect' => $this->multiSelectValue($name, $value, (array) ($field['options'] ?? [])),
            'json' => $this->jsonValue($name, $value),
            default => throw new InvalidArgumentException("Adaptive record field type [{$type}] is not supported."),
        };
    }

    private function stringValue(string $name, string $type, mixed $value): string
    {
        if (! is_scalar($value)) {
            throw new InvalidArgumentException("Adaptive record field [{$name}] must be text.");
        }
        $value = trim((string) $value);
        if (strlen($value) > ($type === 'textarea' ? 20000 : 2000)) {
            throw new InvalidArgumentException("Adaptive record field [{$name}] exceeds its maximum length.");
        }
        if ($type === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Adaptive record field [{$name}] must be a valid email address.");
        }
        if ($type === 'url' && filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Adaptive record field [{$name}] must be a valid URL.");
        }
        return $value;
    }

    /** @param array<string,mixed> $field */
    private function referenceValue(array $field, mixed $value, ?int $companyId): string
    {
        $name = (string) $field['name'];
        $identifier = $this->stringValue($name, 'reference', $value);
        $referenceType = trim((string) ($field['reference_type'] ?? ''));
        if ($companyId === null || $this->references === null) {
            throw new InvalidArgumentException("Adaptive record reference [{$name}] cannot be validated without company context.");
        }
        $this->references->assertAccessible($companyId, $referenceType, $identifier, $name);

        return $identifier;
    }

    private function integerValue(string $name, mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException("Adaptive record field [{$name}] must be an integer.");
        }
        return (int) $value;
    }

    private function numberValue(string $name, mixed $value): float|int
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Adaptive record field [{$name}] must be numeric.");
        }
        return is_int($value) ? $value : (float) $value;
    }

    private function booleanValue(string $name, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (in_array($value, [0, 1, '0', '1'], true)) {
            return (bool) $value;
        }
        throw new InvalidArgumentException("Adaptive record field [{$name}] must be boolean.");
    }

    private function dateValue(string $name, mixed $value, string $format): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("Adaptive record field [{$name}] must use {$format} format.");
        }
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format($format) !== $value) {
            throw new InvalidArgumentException("Adaptive record field [{$name}] must use {$format} format.");
        }
        return $value;
    }

    private function dateTimeValue(string $name, mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("Adaptive record field [{$name}] must be an ISO-8601 datetime.");
        }
        try {
            return (new DateTimeImmutable($value))->format(DATE_ATOM);
        } catch (\Throwable) {
            throw new InvalidArgumentException("Adaptive record field [{$name}] must be an ISO-8601 datetime.");
        }
    }

    /** @param array<int,string> $options */
    private function selectValue(string $name, mixed $value, array $options): string
    {
        $value = (string) $value;
        if (! in_array($value, $options, true)) {
            throw new InvalidArgumentException("Adaptive record field [{$name}] must be an allowed option.");
        }
        return $value;
    }

    /** @param array<int,string> $options @return array<int,string> */
    private function multiSelectValue(string $name, mixed $value, array $options): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("Adaptive record field [{$name}] must be a list of allowed options.");
        }
        $values = array_values(array_unique(array_map('strval', $value)));
        foreach ($values as $selected) {
            if (! in_array($selected, $options, true)) {
                throw new InvalidArgumentException("Adaptive record field [{$name}] contains an unrecognised option.");
            }
        }
        return $values;
    }

    private function jsonValue(string $name, mixed $value): mixed
    {
        if (! is_array($value) && ! is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException("Adaptive record field [{$name}] must be JSON-compatible data.");
        }
        json_encode($value, JSON_THROW_ON_ERROR);
        return $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Tools;

final class ToolDefinitionFactory
{
    /** @param array<string,mixed> $schema @param list<string> $channels */
    public static function read(
        string $name,
        string $description,
        string $target,
        array $schema,
        string $classification = 'confidential',
        array $channels = ['internal'],
    ): AiToolDefinition {
        return new AiToolDefinition(
            name: $name,
            description: $description,
            kind: 'read_model',
            target: $target,
            inputSchema: $schema,
            dataClassification: $classification,
            channels: $channels,
        );
    }

    /** @param array<string,mixed> $schema @param list<string> $channels */
    public static function action(
        string $name,
        string $description,
        string $target,
        array $schema,
        string $classification = 'confidential',
        array $channels = ['internal'],
    ): AiToolDefinition {
        return new AiToolDefinition(
            name: $name,
            description: $description,
            kind: 'action',
            target: $target,
            inputSchema: $schema,
            dataClassification: $classification,
            channels: $channels,
        );
    }

    /** @param array<string,array<string,mixed>> $properties @param list<string> $required @return array<string,mixed> */
    public static function object(array $properties = [], array $required = [], bool $additionalProperties = false): array
    {
        $schema = ['type' => 'object', 'properties' => $properties, 'additionalProperties' => $additionalProperties];
        if ($required !== []) {
            $schema['required'] = $required;
        }
        return $schema;
    }

    /** @return array<string,mixed> */
    public static function string(int $maxLength = 255, ?array $enum = null): array
    {
        $schema = ['type' => 'string', 'maxLength' => $maxLength];
        if ($enum !== null) {
            $schema['enum'] = array_values($enum);
        }
        return $schema;
    }

    /** @return array<string,mixed> */
    public static function integer(int $minimum = 0, ?int $maximum = null): array
    {
        $schema = ['type' => 'integer', 'minimum' => $minimum];
        if ($maximum !== null) {
            $schema['maximum'] = $maximum;
        }
        return $schema;
    }

    /** @return array<string,mixed> */
    public static function number(float $minimum = 0, ?float $maximum = null): array
    {
        $schema = ['type' => 'number', 'minimum' => $minimum];
        if ($maximum !== null) {
            $schema['maximum'] = $maximum;
        }
        return $schema;
    }

    /** @return array<string,mixed> */
    public static function boolean(): array { return ['type' => 'boolean']; }

    /** @return array<string,mixed> */
    public static function freeObject(): array { return ['type' => 'object', 'additionalProperties' => true]; }

    /** @return array<string,mixed> */
    public static function stringArray(int $maxItems = 100): array
    {
        return ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => $maxItems];
    }
}

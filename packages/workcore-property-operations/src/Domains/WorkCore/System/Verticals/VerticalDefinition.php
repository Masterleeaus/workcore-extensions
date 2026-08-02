<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals;

use InvalidArgumentException;

final readonly class VerticalDefinition
{
    /**
     * @param list<string> $extends
     * @param list<string> $capabilities
     * @param array<string,bool> $features
     * @param array<string,string> $terminology
     * @param array<string,mixed> $setup
     * @param array<string,array<string,mixed>> $codebooks
     * @param array<string,array<string,mixed>> $workflows
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $version,
        public string $type,
        public array $extends = [],
        public array $capabilities = [],
        public array $features = [],
        public array $terminology = [],
        public array $setup = [],
        public array $codebooks = [],
        public array $workflows = [],
        public array $metadata = [],
        public ?string $source = null,
    ) {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            throw new InvalidArgumentException("Invalid vertical key [{$key}].");
        }
        if (trim($label) === '' || trim($version) === '') {
            throw new InvalidArgumentException('Vertical label and version are required.');
        }
        if (! in_array($type, ['foundation', 'vertical', 'addon'], true)) {
            throw new InvalidArgumentException("Invalid vertical type [{$type}].");
        }
        foreach ($extends as $dependency) {
            if (! is_string($dependency) || ! preg_match('/^[a-z][a-z0-9_]*$/', $dependency)) {
                throw new InvalidArgumentException("Invalid dependency in vertical [{$key}].");
            }
        }
        foreach ($capabilities as $capability) {
            if (! is_string($capability) || trim($capability) === '') {
                throw new InvalidArgumentException("Invalid capability in vertical [{$key}].");
            }
        }
        foreach ($features as $feature => $enabled) {
            if (! is_string($feature) || $feature === '' || ! is_bool($enabled)) {
                throw new InvalidArgumentException("Invalid feature declaration in vertical [{$key}].");
            }
        }
        foreach ($terminology as $term => $value) {
            if (! is_string($term) || $term === '' || ! is_string($value) || $value === '') {
                throw new InvalidArgumentException("Invalid terminology declaration in vertical [{$key}].");
            }
        }
        foreach ($codebooks as $codebook => $rule) {
            if (! is_string($codebook) || $codebook === '' || ! is_array($rule)) {
                throw new InvalidArgumentException("Invalid codebook declaration in vertical [{$key}].");
            }
        }
        foreach ($workflows as $workflow => $definition) {
            if (! is_string($workflow) || $workflow === '' || ! is_array($definition)) {
                throw new InvalidArgumentException("Invalid workflow declaration in vertical [{$key}].");
            }
        }
    }

    /** @param array<string,mixed> $manifest */
    public static function fromArray(array $manifest, ?string $source = null): self
    {
        $metadata = (array) ($manifest['metadata'] ?? []);
        foreach (['setup', 'codebooks', 'workflows'] as $section) {
            if (isset($manifest[$section]) && ! is_array($manifest[$section])) {
                throw new InvalidArgumentException("Vertical {$section} declaration must be an object.");
            }
        }

        return new self(
            key: (string) ($manifest['key'] ?? ''),
            label: (string) ($manifest['label'] ?? ''),
            version: (string) ($manifest['version'] ?? ''),
            type: (string) ($manifest['type'] ?? 'vertical'),
            extends: array_values(array_unique(array_map('strval', (array) ($manifest['extends'] ?? [])))),
            capabilities: array_values(array_unique(array_map('strval', (array) ($manifest['capabilities'] ?? [])))),
            features: self::booleanMap((array) ($manifest['features'] ?? [])),
            terminology: self::stringMap((array) ($manifest['terminology'] ?? [])),
            setup: (array) ($manifest['setup'] ?? []),
            codebooks: (array) ($manifest['codebooks'] ?? []),
            workflows: (array) ($manifest['workflows'] ?? []),
            metadata: $metadata,
            source: $source,
        );
    }

    /** @param array<mixed> $values @return array<string,bool> */
    private static function booleanMap(array $values): array
    {
        $normalised = [];
        foreach ($values as $key => $value) {
            if (! is_bool($value)) {
                throw new InvalidArgumentException("Feature [{$key}] must be boolean.");
            }
            $normalised[(string) $key] = $value;
        }

        return $normalised;
    }

    /** @param array<mixed> $values @return array<string,string> */
    private static function stringMap(array $values): array
    {
        $normalised = [];
        foreach ($values as $key => $value) {
            if (! is_string($value)) {
                throw new InvalidArgumentException("Terminology [{$key}] must be a string.");
            }
            $normalised[(string) $key] = $value;
        }

        return $normalised;
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Setup;

use InvalidArgumentException;

final readonly class SetupQuestion
{
    /** @param list<string> $options @param list<array<string,mixed>> $appliesWhen @param array<string,mixed> $metadata */
    public function __construct(
        public string $key,
        public string $section,
        public string $prompt,
        public string $type,
        public bool $required = false,
        public array $options = [],
        public mixed $default = null,
        public ?string $help = null,
        public ?string $mapsTo = null,
        public array $appliesWhen = [],
        public array $metadata = [],
        public ?string $sourcePack = null,
    ) {
        if (! preg_match('/^[a-z][a-z0-9_.-]*$/', $key)) {
            throw new InvalidArgumentException("Invalid setup question key [{$key}].");
        }
        if (trim($section) === '' || trim($prompt) === '') {
            throw new InvalidArgumentException("Setup question [{$key}] requires a section and prompt.");
        }
        if (! in_array($type, ['text', 'textarea', 'boolean', 'select', 'multiselect', 'list', 'business_lines', 'locale_map', 'document_multiselect', 'integer'], true)) {
            throw new InvalidArgumentException("Unsupported setup question type [{$type}] for [{$key}].");
        }
        foreach ($options as $option) {
            if (! is_string($option) || trim($option) === '') {
                throw new InvalidArgumentException("Question [{$key}] contains an invalid option.");
            }
        }
    }

    /** @param array<string,mixed> $definition */
    public static function fromArray(array $definition, ?string $sourcePack = null): self
    {
        return new self(
            key: (string) ($definition['key'] ?? ''),
            section: (string) ($definition['section'] ?? 'general'),
            prompt: (string) ($definition['prompt'] ?? ''),
            type: (string) ($definition['type'] ?? 'text'),
            required: (bool) ($definition['required'] ?? false),
            options: array_values(array_unique(array_map('strval', (array) ($definition['options'] ?? [])))),
            default: $definition['default'] ?? null,
            help: isset($definition['help']) ? (string) $definition['help'] : null,
            mapsTo: isset($definition['maps_to']) ? (string) $definition['maps_to'] : null,
            appliesWhen: array_values((array) ($definition['applies_when'] ?? [])),
            metadata: (array) ($definition['metadata'] ?? []),
            sourcePack: $sourcePack,
        );
    }

    /** @param array<string,mixed> $answers @param list<string> $packKeys */
    public function applies(array $answers, array $packKeys): bool
    {
        foreach ($this->appliesWhen as $condition) {
            if (! is_array($condition)) {
                return false;
            }
            if (isset($condition['pack']) && ! in_array((string) $condition['pack'], $packKeys, true)) {
                return false;
            }
            if (! isset($condition['answer'])) {
                continue;
            }
            $value = $answers[(string) $condition['answer']] ?? null;
            if (array_key_exists('equals', $condition) && $value !== $condition['equals']) {
                return false;
            }
            if (isset($condition['in']) && ! in_array($value, (array) $condition['in'], true)) {
                return false;
            }
            if (array_key_exists('truthy', $condition) && (bool) $value !== (bool) $condition['truthy']) {
                return false;
            }
            if (array_key_exists('contains', $condition) && (! is_array($value) || ! in_array($condition['contains'], $value, true))) {
                return false;
            }
        }
        return true;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key, 'section' => $this->section, 'prompt' => $this->prompt,
            'help' => $this->help, 'type' => $this->type, 'required' => $this->required,
            'options' => $this->options, 'default' => $this->default, 'maps_to' => $this->mapsTo,
            'source_pack' => $this->sourcePack, 'metadata' => $this->metadata,
        ];
    }
}

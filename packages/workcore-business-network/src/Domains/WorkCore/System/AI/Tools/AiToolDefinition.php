<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Tools;

use InvalidArgumentException;

final readonly class AiToolDefinition
{
    /**
     * @param array<string,mixed> $inputSchema
     * @param array<string,mixed> $outputSchema
     * @param list<string> $channels
     * @param list<string> $agents
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $kind,
        public string $target,
        public array $inputSchema,
        public array $outputSchema = [],
        public ?string $capability = null,
        public ?string $permission = null,
        public string $risk = 'low',
        public bool $requiresConfirmation = false,
        public string $dataClassification = 'internal',
        public array $channels = ['internal'],
        public array $agents = ['*'],
        public bool $enabled = true,
        public array $metadata = [],
    ) {
        if (! preg_match('/^[a-z][a-z0-9_.-]{2,127}$/', $name)) {
            throw new InvalidArgumentException("Invalid AI tool name [{$name}].");
        }
        if (trim($description) === '' || trim($target) === '') {
            throw new InvalidArgumentException('AI tool description and target are required.');
        }
        if (! in_array($kind, ['action', 'read_model'], true)) {
            throw new InvalidArgumentException("Invalid AI tool kind [{$kind}].");
        }
        if (! in_array($risk, ['low', 'medium', 'high', 'critical'], true)) {
            throw new InvalidArgumentException("Invalid AI tool risk [{$risk}].");
        }
        if (! in_array($dataClassification, ['public', 'internal', 'confidential', 'restricted'], true)) {
            throw new InvalidArgumentException("Invalid data classification [{$dataClassification}].");
        }
        if (($inputSchema['type'] ?? null) !== 'object') {
            throw new InvalidArgumentException('AI tool input schema must be a JSON-schema object.');
        }
        if ($kind === 'read_model' && $requiresConfirmation) {
            throw new InvalidArgumentException('Read-model tools cannot require write confirmation.');
        }
    }

    public function withGovernance(?string $capability, ?string $permission, string $risk, bool $requiresConfirmation): self
    {
        return new self(
            name: $this->name,
            description: $this->description,
            kind: $this->kind,
            target: $this->target,
            inputSchema: $this->inputSchema,
            outputSchema: $this->outputSchema,
            capability: $capability,
            permission: $permission,
            risk: $risk,
            requiresConfirmation: $requiresConfirmation,
            dataClassification: $this->dataClassification,
            channels: $this->channels,
            agents: $this->agents,
            enabled: $this->enabled,
            metadata: $this->metadata,
        );
    }

    /** @return array<string,mixed> */
    public function toModelTool(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->inputSchema,
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals;

final readonly class CompiledVerticalProfile
{
    /**
     * @param list<string> $packKeys
     * @param list<string> $capabilities
     * @param array<string,bool> $features
     * @param array<string,string> $terminology
     * @param array<string,mixed> $metadata
     * @param array<string,array<string,mixed>> $codebooks
     * @param array<string,array<string,mixed>> $workflows
     */
    public function __construct(
        public ?string $primaryVertical,
        public array $packKeys,
        public array $capabilities,
        public array $features,
        public array $terminology,
        public array $metadata = [],
        public array $codebooks = [],
        public array $workflows = [],
    ) {}

    public function isConfigured(): bool
    {
        return $this->primaryVertical !== null;
    }

    public function hasPack(string $key): bool
    {
        return in_array($key, $this->packKeys, true);
    }

    public function supportsCapability(string $capability): bool
    {
        return ! $this->isConfigured() || in_array($capability, $this->capabilities, true);
    }

    public function featureEnabled(string $feature, bool $default = true): bool
    {
        if (! $this->isConfigured()) {
            return true;
        }

        return $this->features[$feature] ?? $default;
    }

    public function term(string $key, ?string $fallback = null): string
    {
        return $this->terminology[$key] ?? $fallback ?? $key;
    }

    /** @return array<string,mixed> */
    public function codebook(string $key): array
    {
        return (array) ($this->codebooks[$key] ?? []);
    }

    /** @return array<string,mixed> */
    public function workflow(string $key): array
    {
        return (array) ($this->workflows[$key] ?? []);
    }
}

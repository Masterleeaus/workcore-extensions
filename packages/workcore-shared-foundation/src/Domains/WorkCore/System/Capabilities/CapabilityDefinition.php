<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Capabilities;

use InvalidArgumentException;

final readonly class CapabilityDefinition
{
    /** @param array<int,string> $requires @param array<string,mixed> $metadata */
    public function __construct(
        public string $key,
        public string $provider,
        public string $version,
        public array $requires = [],
        public array $metadata = [],
    ) {
        if ($key === '' || $provider === '' || $version === '') {
            throw new InvalidArgumentException('Capability key, provider and version are required.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'provider' => $this->provider,
            'version' => $this->version,
            'requires' => $this->requires,
            'metadata' => $this->metadata,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\DTO;

use InvalidArgumentException;

final readonly class MemoryItem
{
    public function __construct(
        public string $publicId,
        public int $companyId,
        public int $ownerActorId,
        public ?string $agentPublicId,
        public string $scope,
        public string $key,
        public string $value,
        public float $confidence,
        public string $source,
        public ?string $expiresAt = null,
    ) {
        if (! in_array($scope, ['user', 'company'], true)) {
            throw new InvalidArgumentException("Invalid AI memory scope [{$scope}].");
        }
        if (trim($key) === '' || trim($value) === '' || $confidence < 0 || $confidence > 1) {
            throw new InvalidArgumentException('AI memory key, value and confidence are invalid.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'public_id' => $this->publicId,
            'scope' => $this->scope,
            'key' => $this->key,
            'value' => $this->value,
            'confidence' => $this->confidence,
            'source' => $this->source,
            'agent_public_id' => $this->agentPublicId,
            'expires_at' => $this->expiresAt,
        ];
    }
}

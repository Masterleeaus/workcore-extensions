<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\DTO;

final readonly class ResolvedModelProfile
{
    /** @param array<string,mixed> $providerSettings @param array<string,mixed> $modelSettings */
    public function __construct(
        public int $companyId,
        public string $providerKey,
        public string $providerName,
        public string $baseUrl,
        public ?string $secretReference,
        public string $model,
        public string $purpose,
        public int $priority = 100,
        public ?string $providerProfilePublicId = null,
        public ?string $modelProfilePublicId = null,
        public array $providerSettings = [],
        public array $modelSettings = [],
    ) {}
}

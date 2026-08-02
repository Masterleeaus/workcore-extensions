<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\DTO;

use InvalidArgumentException;

final readonly class ResolvedAgentProfile
{
    /**
     * @param list<string> $toolAllowlist
     * @param array<string,mixed> $guardrails
     * @param array<string,mixed> $settings
     */
    public function __construct(
        public ?string $publicId,
        public ?string $versionPublicId,
        public string $key,
        public string $name,
        public string $systemPrompt,
        public array $toolAllowlist = ['*'],
        public array $guardrails = [],
        public ?string $model = null,
        public array $settings = [],
    ) {
        if (trim($key) === '' || trim($name) === '' || trim($systemPrompt) === '') {
            throw new InvalidArgumentException('Agent key, name and system prompt are required.');
        }
    }

    public function allowsTool(string $toolName): bool
    {
        foreach ($this->toolAllowlist as $pattern) {
            if ($pattern === '*' || $pattern === $toolName) {
                return true;
            }
            if (str_ends_with($pattern, '*') && str_starts_with($toolName, substr($pattern, 0, -1))) {
                return true;
            }
        }

        return false;
    }
}

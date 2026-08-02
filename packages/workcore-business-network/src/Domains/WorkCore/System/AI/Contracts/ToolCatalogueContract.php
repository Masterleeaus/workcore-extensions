<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Contracts;

use App\Domains\WorkCore\System\AI\Tools\AiToolDefinition;

interface ToolCatalogueContract
{
    public function get(string $name): ?AiToolDefinition;

    /** @return array<string,AiToolDefinition> */
    public function all(): array;

    /** @return array<string,AiToolDefinition> */
    public function available(int $companyId, int $actorId, string $channel = 'internal', string $agent = '*'): array;

    /** @return list<array<string,mixed>> */
    public function modelTools(int $companyId, int $actorId, string $channel = 'internal', string $agent = '*'): array;
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Providers;

use App\Domains\WorkCore\System\AI\Contracts\ModelProviderContract;
use App\Domains\WorkCore\System\AI\DTO\ModelRequest;
use App\Domains\WorkCore\System\AI\DTO\ModelResponse;
use App\Domains\WorkCore\System\AI\Exceptions\AiProviderException;

final class NullModelProvider implements ModelProviderContract
{
    public function __construct(private string $providerKey = 'null') {}

    public function key(): string { return $this->providerKey; }

    public function generate(ModelRequest $request): ModelResponse
    {
        throw new AiProviderException('No AI model provider is configured for this company and purpose.', $this->providerKey, 503, false);
    }

    public function health(): array
    {
        return ['ok' => false, 'provider' => $this->providerKey, 'reason' => 'Provider is intentionally disabled.'];
    }
}

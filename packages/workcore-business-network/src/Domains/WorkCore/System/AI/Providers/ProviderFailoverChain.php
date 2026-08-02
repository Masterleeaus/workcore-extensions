<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Providers;

use App\Domains\WorkCore\System\AI\Contracts\ModelProviderContract;
use App\Domains\WorkCore\System\AI\DTO\ModelRequest;
use App\Domains\WorkCore\System\AI\DTO\ModelResponse;
use App\Domains\WorkCore\System\AI\Exceptions\AiProviderException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ProviderFailoverChain implements ModelProviderContract
{
    /** @var list<ModelProviderContract> */
    private array $providers;

    /** @param iterable<ModelProviderContract> $providers @param list<int> $retryableStatuses */
    public function __construct(iterable $providers, private array $retryableStatuses = [408, 409, 425, 429, 500, 502, 503, 504])
    {
        $this->providers = array_values(is_array($providers) ? $providers : iterator_to_array($providers));
        if ($this->providers === []) {
            throw new InvalidArgumentException('At least one model provider is required.');
        }
        foreach ($this->providers as $provider) {
            if (! $provider instanceof ModelProviderContract) {
                throw new InvalidArgumentException('Every failover provider must implement ModelProviderContract.');
            }
        }
    }

    public function key(): string
    {
        return 'failover:' . implode(',', array_map(static fn (ModelProviderContract $provider): string => $provider->key(), $this->providers));
    }

    public function generate(ModelRequest $request): ModelResponse
    {
        $last = null;
        foreach ($this->providers as $provider) {
            try {
                return $provider->generate($request);
            } catch (Throwable $exception) {
                $last = $exception;
                if (! $this->isRetryable($exception)) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('All configured AI providers failed.', 503, $last);
    }

    public function health(): array
    {
        foreach ($this->providers as $provider) {
            $health = $provider->health();
            if (($health['ok'] ?? false) === true) {
                return ['ok' => true, 'provider' => $provider->key(), 'reason' => null];
            }
        }

        return ['ok' => false, 'provider' => $this->key(), 'reason' => 'No healthy provider in the failover chain.'];
    }

    private function isRetryable(Throwable $exception): bool
    {
        if ($exception instanceof AiProviderException) {
            return $exception->retryable || ($exception->httpStatus !== null && in_array($exception->httpStatus, $this->retryableStatuses, true));
        }

        return in_array((int) $exception->getCode(), $this->retryableStatuses, true);
    }
}

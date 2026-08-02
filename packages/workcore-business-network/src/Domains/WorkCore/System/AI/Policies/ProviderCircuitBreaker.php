<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Policies;

use Illuminate\Contracts\Cache\Repository;

final class ProviderCircuitBreaker
{
    public function __construct(private Repository $cache) {}

    public function allows(int $companyId, string $providerKey): bool
    {
        return ! (bool) $this->cache->get($this->openKey($companyId, $providerKey), false);
    }

    public function recordFailure(int $companyId, string $providerKey): void
    {
        $failuresKey = $this->failureKey($companyId, $providerKey);
        $failures = (int) $this->cache->increment($failuresKey);
        $this->cache->put($failuresKey, $failures, now()->addMinutes(10));
        $threshold = max(1, (int) config('workcore_ai.circuit_breaker.failure_threshold', 3));
        if ($failures >= $threshold) {
            $seconds = max(10, (int) config('workcore_ai.circuit_breaker.open_seconds', 60));
            $this->cache->put($this->openKey($companyId, $providerKey), true, now()->addSeconds($seconds));
        }
    }

    public function recordSuccess(int $companyId, string $providerKey): void
    {
        $this->cache->forget($this->failureKey($companyId, $providerKey));
        $this->cache->forget($this->openKey($companyId, $providerKey));
    }

    private function failureKey(int $companyId, string $providerKey): string { return "workcore-ai:breaker:failures:{$companyId}:{$providerKey}"; }
    private function openKey(int $companyId, string $providerKey): string { return "workcore-ai:breaker:open:{$companyId}:{$providerKey}"; }
}

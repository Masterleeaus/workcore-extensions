<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Policies;

use App\Domains\WorkCore\System\AI\DTO\ModelRequest;
use App\Domains\WorkCore\System\AI\Exceptions\AiPolicyException;
use Illuminate\Cache\RateLimiter;

final class AiRateLimiter
{
    public function __construct(private RateLimiter $limiter) {}

    public function assertAllowed(ModelRequest $request): void
    {
        $limit = max(1, (int) config('workcore_ai.rate_limits.requests_per_minute', 60));
        $key = "workcore-ai:{$request->companyId}:{$request->actorId}:{$request->purpose}";
        if ($this->limiter->tooManyAttempts($key, $limit)) {
            throw new AiPolicyException('Native AI request rate limit exceeded.', 429);
        }
        $this->limiter->hit($key, 60);
    }
}

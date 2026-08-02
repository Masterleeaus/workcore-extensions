<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Providers;

use App\Domains\WorkCore\System\AI\Contracts\{AiRunRecorderContract,ModelGatewayContract,ModelProfileResolverContract};
use App\Domains\WorkCore\System\AI\DTO\{ModelRequest,ModelResponse,ResolvedModelProfile};
use App\Domains\WorkCore\System\AI\Exceptions\{AiConfigurationException,AiProviderException};
use App\Domains\WorkCore\System\AI\Policies\{AiBudgetGuard,AiRateLimiter,ProviderCircuitBreaker};
use App\Domains\WorkCore\System\AI\Security\NativeAiAccessGate;
use Throwable;

final class NativeModelGateway implements ModelGatewayContract
{
    public function __construct(
        private ModelProfileResolverContract $profiles,
        private ModelProviderFactory $factory,
        private AiRateLimiter $rateLimiter,
        private AiBudgetGuard $budget,
        private ProviderCircuitBreaker $breaker,
        private AiRunRecorderContract $runs,
        private NativeAiAccessGate $access,
    ) {}

    public function generate(ModelRequest $request): ModelResponse
    {
        $this->access->assertModelAccess($request->companyId, $request->actorId);
        $this->rateLimiter->assertAllowed($request);
        $this->budget->assertAllowed($request);
        $profiles = $this->profiles->resolve($request);
        if ($profiles === []) {
            throw new AiConfigurationException('No native AI model profile is available for this company and purpose.');
        }

        $runPublicId = $this->runs->start($request, $profiles[0]);
        $last = null;
        $lastProfile = null;
        foreach ($profiles as $profile) {
            $lastProfile = $profile;
            if (! $this->breaker->allows($request->companyId, $profile->providerKey)) {
                continue;
            }
            try {
                $response = $this->factory->make($profile)->generate($request);
                $this->breaker->recordSuccess($request->companyId, $profile->providerKey);
                $this->runs->complete($runPublicId, $response, $profile);
                return $response;
            } catch (Throwable $exception) {
                $last = $exception;
                $this->breaker->recordFailure($request->companyId, $profile->providerKey);
                if ($exception instanceof AiProviderException && ! $exception->retryable) {
                    break;
                }
            }
        }

        $exception = $last ?? new AiProviderException('Every configured AI provider circuit is open.', $lastProfile?->providerKey ?? 'unknown', 503, true);
        $this->runs->fail($runPublicId, $exception, $lastProfile);
        throw $exception;
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Providers;

use App\Domains\WorkCore\System\AI\Contracts\CredentialResolverContract;
use App\Domains\WorkCore\System\AI\Contracts\ModelProviderContract;
use App\Domains\WorkCore\System\AI\DTO\ResolvedModelProfile;
use App\Domains\WorkCore\System\AI\Exceptions\AiConfigurationException;
use Illuminate\Http\Client\Factory;

final class ModelProviderFactory
{
    public function __construct(private Factory $http, private CredentialResolverContract $credentials) {}

    public function make(ResolvedModelProfile $profile): ModelProviderContract
    {
        if ($profile->providerKey === 'null') {
            return new NullModelProvider();
        }

        $compatible = ['openai', 'openai_compatible', 'local', 'ollama'];
        if (! in_array($profile->providerKey, $compatible, true)) {
            throw new AiConfigurationException("Unsupported native AI provider [{$profile->providerKey}].");
        }
        if (trim($profile->baseUrl) === '') {
            throw new AiConfigurationException("AI provider [{$profile->providerKey}] has no base URL.");
        }

        $secret = $profile->secretReference === null ? null : $this->credentials->resolve($profile->secretReference, $profile->companyId);
        return new OpenAICompatibleProvider(
            $this->http,
            $profile->providerKey,
            $profile->baseUrl,
            $profile->model,
            $secret,
            array_replace($profile->providerSettings, $profile->modelSettings),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Providers;

use App\Domains\WorkCore\System\AI\Contracts\ModelProviderContract;
use App\Domains\WorkCore\System\AI\DTO\{ModelRequest,ModelResponse,TokenUsage};
use App\Domains\WorkCore\System\AI\Exceptions\AiProviderException;
use Illuminate\Http\Client\Factory;
use Throwable;

final class OpenAICompatibleProvider implements ModelProviderContract
{
    /** @param array<string,mixed> $settings */
    public function __construct(
        private Factory $http,
        private string $providerKey,
        private string $baseUrl,
        private string $defaultModel,
        private ?string $apiKey = null,
        private array $settings = [],
    ) {}

    public function key(): string { return $this->providerKey; }

    public function generate(ModelRequest $request): ModelResponse
    {
        $started = hrtime(true);
        $payload = $request->toProviderPayload();
        $payload['model'] = $request->model ?: $this->defaultModel;
        $timeout = max(1, min(300, (int) ($this->settings['timeout_seconds'] ?? 60)));

        try {
            $pending = $this->http->acceptJson()->asJson()->timeout($timeout);
            if ($this->apiKey !== null && $this->apiKey !== '') {
                $pending = $pending->withToken($this->apiKey);
            }
            foreach ((array) ($this->settings['headers'] ?? []) as $name => $value) {
                if (! is_string($name) || ! (is_string($value) || is_numeric($value)) || $this->isSecretHeader($name)) {
                    continue;
                }
                $pending = $pending->withHeader($name, (string) $value);
            }
            $response = $pending->post($this->endpoint('chat/completions'), $payload);
        } catch (Throwable $exception) {
            throw new AiProviderException('AI provider request failed.', $this->providerKey, 503, true, ['exception' => $exception::class]);
        }

        if (! $response->successful()) {
            $status = $response->status();
            $message = (string) data_get($response->json(), 'error.message', "Provider returned HTTP {$status}.");
            throw new AiProviderException($message, $this->providerKey, $status, in_array($status, [408,409,425,429,500,502,503,504], true));
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new AiProviderException('AI provider returned an invalid JSON response.', $this->providerKey, 502, true);
        }
        $message = data_get($body, 'choices.0.message', []);
        $content = is_array($message) && array_key_exists('content', $message) ? $message['content'] : null;
        $content = is_string($content) ? $content : null;
        $toolCalls = is_array(data_get($message, 'tool_calls')) ? array_values(data_get($message, 'tool_calls')) : [];
        $structured = [];
        if ($request->responseFormat === 'json' && is_string($content) && $content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $structured = $decoded;
            }
        }
        $inputTokens = (int) data_get($body, 'usage.prompt_tokens', 0);
        $outputTokens = (int) data_get($body, 'usage.completion_tokens', 0);
        $totalTokens = (int) data_get($body, 'usage.total_tokens', $inputTokens + $outputTokens);
        $cached = (int) data_get($body, 'usage.prompt_tokens_details.cached_tokens', 0);

        return new ModelResponse(
            content: $content,
            structured: $structured,
            toolCalls: $toolCalls,
            usage: new TokenUsage($inputTokens, $outputTokens, $totalTokens, $cached),
            provider: $this->providerKey,
            model: (string) ($body['model'] ?? $payload['model']),
            latencyMs: (int) round((hrtime(true) - $started) / 1_000_000),
            providerResponseId: isset($body['id']) ? (string) $body['id'] : null,
            metadata: ['finish_reason' => data_get($body, 'choices.0.finish_reason')],
        );
    }

    private function endpoint(string $path): string
    {
        $base = rtrim($this->baseUrl, '/');
        return str_ends_with($base, '/v1')
            ? $base . '/' . ltrim($path, '/')
            : $base . '/v1/' . ltrim($path, '/');
    }

    private function isSecretHeader(string $name): bool
    {
        return in_array(strtolower(trim($name)), [
            'authorization', 'proxy-authorization', 'api-key', 'x-api-key', 'x-auth-token',
        ], true);
    }

    public function health(): array
    {
        try {
            $pending = $this->http->acceptJson()->timeout(5);
            if ($this->apiKey !== null && $this->apiKey !== '') {
                $pending = $pending->withToken($this->apiKey);
            }
            $response = $pending->get($this->endpoint('models'));
            return ['ok' => $response->successful(), 'provider' => $this->providerKey, 'reason' => $response->successful() ? null : 'Provider health check failed.'];
        } catch (Throwable) {
            return ['ok' => false, 'provider' => $this->providerKey, 'reason' => 'Provider is unreachable.'];
        }
    }
}

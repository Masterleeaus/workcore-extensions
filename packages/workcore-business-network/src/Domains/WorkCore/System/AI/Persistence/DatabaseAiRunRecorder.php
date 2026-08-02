<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Persistence;

use App\Domains\WorkCore\System\AI\Contracts\AiRunRecorderContract;
use App\Domains\WorkCore\System\AI\DTO\{ModelRequest,ModelResponse,ResolvedModelProfile};
use App\Domains\WorkCore\System\AI\Security\SensitiveDataRedactor;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Throwable;

final class DatabaseAiRunRecorder implements AiRunRecorderContract
{
    public function __construct(private ConnectionInterface $db, private SensitiveDataRedactor $redactor) {}

    public function start(ModelRequest $request, ?ResolvedModelProfile $profile = null): string
    {
        $publicId = (string) Str::ulid();
        $snapshot = [
            'purpose' => $request->purpose,
            'requested_model' => $request->model,
            'response_format' => $request->responseFormat,
            'message_count' => count($request->messages),
            'tool_names' => array_values(array_filter(array_map(static fn (array $tool): ?string => data_get($tool, 'function.name'), $request->tools))),
            'metadata' => $this->redactor->redact($request->metadata),
        ];
        $this->db->table('tz_ai_runs')->insert([
            'public_id' => $publicId,
            'company_id' => $request->companyId,
            'actor_user_id' => $request->actorId,
            'agent_public_id' => $request->agentPublicId,
            'model_profile_public_id' => $profile?->modelProfilePublicId,
            'provider_profile_public_id' => $profile?->providerProfilePublicId,
            'purpose' => $request->purpose,
            'status' => 'running',
            'source' => (string) ($request->metadata['source'] ?? 'workcore.native_ai'),
            'thread_public_id' => $request->threadPublicId,
            'request_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
            'request_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'provider_key' => $profile?->providerKey,
            'model_key' => $profile?->model,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $publicId;
    }

    public function complete(string $runPublicId, ModelResponse $response, ?ResolvedModelProfile $profile = null): void
    {
        $snapshot = [
            'content_length' => $response->content === null ? 0 : mb_strlen($response->content),
            'content_hash' => $response->content === null ? null : hash('sha256', $response->content),
            'structured' => $this->redactor->redact($response->structured),
            'tool_call_names' => array_values(array_filter(array_map(static fn (array $call): ?string => data_get($call, 'function.name'), $response->toolCalls))),
            'metadata' => $this->redactor->redact($response->metadata),
        ];
        $this->db->transaction(function () use ($runPublicId, $response, $profile, $snapshot): void {
            $this->db->table('tz_ai_runs')->where('public_id', $runPublicId)->update([
                'status' => 'completed',
                'provider_profile_public_id' => $profile?->providerProfilePublicId,
                'model_profile_public_id' => $profile?->modelProfilePublicId,
                'provider_key' => $response->provider,
                'model_key' => $response->model,
                'provider_response_id' => $response->providerResponseId,
                'response_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'input_tokens' => $response->usage->inputTokens,
                'output_tokens' => $response->usage->outputTokens,
                'cached_input_tokens' => $response->usage->cachedInputTokens,
                'total_tokens' => $response->usage->totalTokens,
                'estimated_cost' => $response->usage->estimatedCost,
                'cost_currency' => $response->usage->currency,
                'latency_ms' => $response->latencyMs,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
            $run = $this->db->table('tz_ai_runs')->where('public_id', $runPublicId)->first(['id','company_id']);
            if ($run !== null) {
                $this->db->table('tz_ai_usage_ledger')->insert([
                    'public_id' => (string) Str::ulid(),
                    'company_id' => (int) $run->company_id,
                    'ai_run_id' => (int) $run->id,
                    'provider_key' => $response->provider,
                    'model_key' => $response->model,
                    'input_tokens' => $response->usage->inputTokens,
                    'output_tokens' => $response->usage->outputTokens,
                    'cached_input_tokens' => $response->usage->cachedInputTokens,
                    'total_tokens' => $response->usage->totalTokens,
                    'estimated_cost' => $response->usage->estimatedCost,
                    'cost_currency' => $response->usage->currency,
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function fail(string $runPublicId, Throwable $exception, ?ResolvedModelProfile $profile = null): void
    {
        $this->db->table('tz_ai_runs')->where('public_id', $runPublicId)->update([
            'status' => 'failed',
            'provider_profile_public_id' => $profile?->providerProfilePublicId,
            'model_profile_public_id' => $profile?->modelProfilePublicId,
            'provider_key' => $profile?->providerKey,
            'model_key' => $profile?->model,
            'error_code' => (string) $exception->getCode(),
            'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

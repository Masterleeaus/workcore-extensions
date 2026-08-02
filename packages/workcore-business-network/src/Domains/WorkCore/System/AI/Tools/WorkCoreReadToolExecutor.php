<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Tools;

use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\AI\DTO\{AiToolExecutionContext,AiToolResult};
use App\Domains\WorkCore\System\AI\Exceptions\AiToolException;
use App\Domains\WorkCore\System\Contracts\{OperationContextContract,PermissionResolverContract,TenantContextContract};
use App\Domains\WorkCore\System\ReadModels\ReadModelRegistry;
use Illuminate\Contracts\Container\Container;
use JsonSerializable;

final class WorkCoreReadToolExecutor
{
    public function __construct(
        private Container $container,
        private ReadModelRegistry $reads,
        private TenantContextContract $tenant,
        private OperationContextContract $operation,
        private EntitlementResolverContract $entitlements,
        private PermissionResolverContract $permissions,
        private JsonSchemaValidator $validator,
    ) {}

    /** @param array<string,mixed> $input */
    public function execute(AiToolDefinition $tool, array $input, AiToolExecutionContext $context): AiToolResult
    {
        $definition = $this->reads->get($tool->target);
        if ($tool->kind !== 'read_model' || $definition === null) {
            throw new AiToolException("AI tool [{$tool->name}] does not target a registered WorkCore read model.", 404);
        }
        if (! $this->tenant->hasTenant() || $this->tenant->companyId() !== $context->companyId || $this->tenant->userId() !== $context->actorId) {
            throw new AiToolException('AI read request does not match the active WorkCore tenant.', 409);
        }
        if (! $this->operation->hasContext() || $this->operation->companyId() !== $context->companyId || $this->operation->actorId() !== $context->actorId) {
            throw new AiToolException('AI read request does not match the active WorkCore operation context.', 409);
        }
        if (! $this->entitlements->allows($context->companyId, $definition->capability)) {
            throw new AiToolException('The company is not entitled to this AI read tool.', 403);
        }
        if ($definition->permission !== null && ! $this->permissions->allows($context->actorId, $context->companyId, $definition->permission)) {
            throw new AiToolException('The actor is not permitted to use this AI read tool.', 403);
        }
        $errors = $this->validator->validate($input, $tool->inputSchema);
        if ($errors !== []) {
            throw new AiToolException('AI tool input validation failed.', 422, $errors);
        }

        $handler = $this->container->make($definition->handler);
        $method = $this->resolveMethod($handler, (string) ($definition->metadata['method'] ?? ''));
        $started = hrtime(true);
        $parameters = array_merge($input, [
            'companyId' => $context->companyId,
            'actorId' => $context->actorId,
            'company_id' => $context->companyId,
            'actor_id' => $context->actorId,
        ]);
        $value = $method === '__invoke'
            ? $this->container->call($handler, $parameters)
            : $this->container->call([$handler, $method], $parameters);
        $data = $this->normalise($value);

        return new AiToolResult(
            tool: $tool->name,
            target: $tool->target,
            kind: 'read_model',
            data: $data,
            latencyMs: (int) round((hrtime(true) - $started) / 1_000_000),
        );
    }

    private function resolveMethod(object $handler, string $preferred): string
    {
        if ($preferred !== '' && method_exists($handler, $preferred)) {
            return $preferred;
        }
        foreach (['execute', 'search', 'handle', '__invoke'] as $method) {
            if (method_exists($handler, $method)) {
                return $method;
            }
        }
        throw new AiToolException('The WorkCore read-model handler has no supported execution method.', 500);
    }

    /** @return array<string,mixed> */
    private function normalise(mixed $value): array
    {
        if (is_array($value)) {
            return array_is_list($value) ? ['items' => $value] : $value;
        }
        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
            return is_array($value) ? (array_is_list($value) ? ['items' => $value] : $value) : ['value' => $value];
        }
        if (is_object($value) && method_exists($value, 'toArray')) {
            $array = $value->toArray();
            return is_array($array) ? (array_is_list($array) ? ['items' => $array] : $array) : ['value' => $array];
        }
        return ['value' => $value];
    }
}

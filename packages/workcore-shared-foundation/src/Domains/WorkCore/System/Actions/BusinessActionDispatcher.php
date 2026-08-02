<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions;

use App\Domains\WorkCore\System\Actions\Contracts\ActionAuditRecorderContract;
use App\Domains\WorkCore\System\Actions\Contracts\ActionIdempotencyStoreContract;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\Contracts\ConfirmationVerifierContract;
use App\Domains\WorkCore\System\Actions\Contracts\DomainEventRecorderContract;
use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\Actions\Exceptions\ActionDispatchException;
use App\Domains\WorkCore\System\Contracts\OperationContextContract;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Throwable;

final class BusinessActionDispatcher
{
    public function __construct(
        private Container $container,
        private ConnectionInterface $db,
        private BusinessActionRegistry $registry,
        private TenantContextContract $tenant,
        private OperationContextContract $operation,
        private PermissionResolverContract $permissions,
        private EntitlementResolverContract $entitlements,
        private ConfirmationVerifierContract $confirmations,
        private ActionIdempotencyStoreContract $idempotency,
        private ActionAuditRecorderContract $audit,
        private DomainEventRecorderContract $events,
    ) {}

    public function dispatch(ActionRequest $request): ActionResult
    {
        $definition = $this->registry->get($request->key)
            ?? throw new ActionDispatchException("Business action [{$request->key}] is not registered.");

        $correlationId = $this->operation->correlationId();
        $this->assertContext($definition, $request, $correlationId);

        if (! $this->entitlements->allows($request->companyId, $definition->capability)) {
            $this->reject($definition, $request, $correlationId, 'The company is not entitled to this action.', 403);
        }
        if ($definition->permission !== null && ! $this->permissions->allows($request->actorId, $request->companyId, $definition->permission)) {
            $this->reject($definition, $request, $correlationId, 'The actor is not permitted to execute this action.', 403);
        }
        if (! $this->confirmations->verify($definition, $request)) {
            $this->reject($definition, $request, $correlationId, 'Explicit confirmation is required for this action.', 428);
        }

        if ($replay = $this->idempotency->replay($request)) {
            return $replay;
        }

        try {
            return $this->db->transaction(function () use ($request, $definition, $correlationId): ActionResult {
                $this->idempotency->reserve($request);
                $handler = $this->container->make($definition->handler);
                if (! $handler instanceof BusinessActionHandlerContract) {
                    throw new ActionDispatchException("Action handler [{$definition->handler}] must implement BusinessActionHandlerContract.");
                }

                $handlerResult = $handler->handle($request);
                $eventIds = $this->events->record($request, $handlerResult, $correlationId, $this->operation->causationId());
                $auditId = $this->audit->recordCompleted($definition, $request, $handlerResult, $correlationId);
                $result = new ActionResult($request->key, $handlerResult->data, $correlationId, $auditId, $eventIds);
                $this->idempotency->complete($request, $result);

                return $result;
            }, 3);
        } catch (Throwable $exception) {
            try {
                $this->db->transaction(function () use ($request, $definition, $correlationId, $exception): void {
                    $this->idempotency->fail($request, $exception->getMessage());
                    $this->audit->recordFailed($definition, $request, $correlationId, $exception->getMessage());
                });
            } catch (Throwable) {
                // Preserve the original exception. Pass 14 will add a durable failure outbox.
            }

            throw $exception;
        }
    }

    private function assertContext(ActionDefinition $definition, ActionRequest $request, string $correlationId): void
    {
        if ($this->tenant->companyId() !== $request->companyId || $this->tenant->userId() !== $request->actorId) {
            $this->reject($definition, $request, $correlationId, 'Action request does not match the active WorkCore tenant.', 409);
        }
        if ($this->operation->companyId() !== $request->companyId || $this->operation->actorId() !== $request->actorId) {
            $this->reject($definition, $request, $correlationId, 'Action request does not match the active WorkCore operation context.', 409);
        }
    }

    private function reject(ActionDefinition $definition, ActionRequest $request, string $correlationId, string $message, int $status): never
    {
        try {
            $this->audit->recordFailed($definition, $request, $correlationId, $message);
        } catch (Throwable) {
            // Rejection must preserve the original policy decision even if audit persistence is unavailable.
        }

        throw new ActionDispatchException($message, $status);
    }
}

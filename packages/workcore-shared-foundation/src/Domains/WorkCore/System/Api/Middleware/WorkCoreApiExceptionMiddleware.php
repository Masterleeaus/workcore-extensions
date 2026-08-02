<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Api\Middleware;

use App\Domains\WorkCore\System\Contracts\OperationContextContract;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class WorkCoreApiExceptionMiddleware
{
    public function __construct(private OperationContextContract $context) {}

    public function handle(Request $request, Closure $next): mixed
    {
        try {
            return $next($request);
        } catch (ValidationException $exception) {
            return $this->error('validation_failed', 'The request data is invalid.', 422, $exception->errors());
        } catch (AuthenticationException) {
            return $this->error('unauthenticated', 'Authentication is required.', 401);
        } catch (AuthorizationException) {
            return $this->error('forbidden', 'You are not authorised to perform this action.', 403);
        } catch (HttpExceptionInterface $exception) {
            return $this->error($this->codeFor($exception->getStatusCode()), $exception->getMessage() ?: 'The request could not be completed.', $exception->getStatusCode());
        } catch (Throwable $exception) {
            report($exception);
            return $this->error('internal_error', 'The request could not be completed.', 500);
        }
    }

    /** @param array<string,mixed> $details */
    private function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'data' => null,
            'meta' => (object) [],
            'errors' => [[
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
            ]],
            'correlation_id' => $this->context->hasContext() ? $this->context->correlationId() : null,
        ], $status);
    }

    private function codeFor(int $status): string
    {
        return match ($status) {
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            409 => 'conflict',
            422 => 'unprocessable_entity',
            429 => 'rate_limited',
            default => 'http_error',
        };
    }
}

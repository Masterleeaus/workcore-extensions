<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Execution;

use Throwable;

final class IntelligenceFailureClassifier
{
    public function classify(Throwable $exception): FailureClassification
    {
        $message = strtolower($exception->getMessage());
        if ($exception instanceof \InvalidArgumentException || str_contains($message, 'schema') || str_contains($message, 'validation')) {
            return new FailureClassification('validation', false, 'INTELLIGENCE_VALIDATION_FAILED', 'The intelligence request or response was invalid.');
        }
        if (str_contains($message, 'permission') || str_contains($message, 'forbidden') || str_contains($message, 'unauthor')) {
            return new FailureClassification('policy', false, 'INTELLIGENCE_POLICY_REJECTED', 'The operation was rejected by policy.');
        }
        if (str_contains($message, '429') || str_contains($message, 'rate limit') || str_contains($message, 'too many requests')) {
            return new FailureClassification('rate_limit', true, 'INTELLIGENCE_RATE_LIMITED', 'The intelligence provider is temporarily rate limited.');
        }
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out') || str_contains($message, 'connection') || str_contains($message, 'temporar')) {
            return new FailureClassification('transient_dependency', true, 'INTELLIGENCE_DEPENDENCY_UNAVAILABLE', 'A required intelligence dependency is temporarily unavailable.');
        }
        if (str_contains($message, 'budget') || str_contains($message, 'quota')) {
            return new FailureClassification('budget', false, 'INTELLIGENCE_BUDGET_EXCEEDED', 'The intelligence execution budget was exceeded.');
        }

        return new FailureClassification('runtime', false, 'INTELLIGENCE_RUNTIME_FAILURE', 'The intelligence operation failed.');
    }
}

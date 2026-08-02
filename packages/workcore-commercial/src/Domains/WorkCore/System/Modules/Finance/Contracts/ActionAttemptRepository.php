<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;

interface ActionAttemptRepository
{
    /** @param array<string,mixed> $result */
    public function record(string $actionKey, string $state, int $attemptNumber, array $result, ActionContext $context, ?string $errorCode = null): void;
}

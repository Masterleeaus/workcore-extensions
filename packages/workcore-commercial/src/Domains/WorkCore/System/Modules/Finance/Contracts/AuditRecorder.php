<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Audit\AuditActor;

interface AuditRecorder
{
    /** @param array<string, mixed> $metadata */
    public function record(
        string $actionKey,
        string $outcome,
        ActionContext $context,
        AuditActor $actor,
        array $metadata = [],
    ): void;
}

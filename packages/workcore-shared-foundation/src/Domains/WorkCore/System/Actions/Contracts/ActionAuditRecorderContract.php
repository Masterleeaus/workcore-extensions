<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Contracts;

use App\Domains\WorkCore\System\Actions\ActionDefinition;
use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;

interface ActionAuditRecorderContract
{
    public function recordCompleted(ActionDefinition $definition, ActionRequest $request, ActionHandlerResult $result, string $correlationId): string;
    public function recordFailed(ActionDefinition $definition, ActionRequest $request, string $correlationId, string $message): string;
}

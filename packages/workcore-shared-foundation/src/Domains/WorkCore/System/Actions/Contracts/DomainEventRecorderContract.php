<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Contracts;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;

interface DomainEventRecorderContract
{
    /** @return array<int,string> */
    public function record(ActionRequest $request, ActionHandlerResult $result, string $correlationId, ?string $causationId): array;
}

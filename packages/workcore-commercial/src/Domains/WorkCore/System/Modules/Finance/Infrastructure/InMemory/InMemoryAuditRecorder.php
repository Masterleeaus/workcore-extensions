<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Audit\AuditActor;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AuditRecorder;

final class InMemoryAuditRecorder implements AuditRecorder
{
    /** @var list<array<string,mixed>> */ public array $events=[];
    public function record(string $actionKey, string $outcome, ActionContext $context, AuditActor $actor, array $metadata=[]): void { $this->events[]=compact('actionKey','outcome','context','actor','metadata'); }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AutomationScheduler;
use DateTimeImmutable;

final class InMemoryAutomationScheduler implements AutomationScheduler
{
    /** @var array<string,array<string,mixed>> */ public array $scheduled=[];
    public function schedule(string $actionKey, array $payload, DateTimeImmutable $runAt, ActionContext $context): string { $id='scheduled-'.(count($this->scheduled)+1); $this->scheduled[$id]=compact('actionKey','payload','runAt','context'); return $id; }
    public function cancel(string $scheduledActionId, ActionContext $context): void { unset($this->scheduled[$scheduledActionId]); }
    public function cancelForEntity(string $companyId,string $entityType,string $entityId,ActionContext $context): int { $count=0; foreach($this->scheduled as $id=>$record){ if(($record['payload'][$entityType.'_id']??null)===$entityId){unset($this->scheduled[$id]);$count++;}} return $count; }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Actions;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\BusinessActionHandlerContract;
use App\Domains\WorkCore\System\Actions\PendingDomainEvent;
use App\Domains\WorkCore\System\Intelligence\Knowledge\KnowledgeIndexStatusContract;

final class PurgeExpiredKnowledge implements BusinessActionHandlerContract
{
    public function __construct(private KnowledgeIndexStatusContract $index) {}

    public function handle(ActionRequest $request): ActionHandlerResult
    {
        $before = isset($request->payload['before']) ? trim((string) $request->payload['before']) : null;
        $count = $this->index->purgeExpired($request->companyId, $before !== '' ? $before : null);

        return new ActionHandlerResult(
            data: ['purged' => $count, 'before' => $before],
            events: [new PendingDomainEvent('workcore.intelligence.knowledge_purged', 1, ['purged' => $count, 'before' => $before])],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\OutboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Events\OutboxEvent;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabaseOutboxRepository implements OutboxRepository
{
    public function append(OutboxEvent $event): void
    {
        $actor=$event->context->actor??throw new RuntimeException('Audit actor required.');
        DB::table('tm_outbox_events')->insert(['id'=>$event->id,'company_id'=>$event->companyId,'event_type'=>$event->eventType,'aggregate_type'=>$event->aggregateType,'aggregate_id'=>$event->aggregateId,
            'aggregate_version'=>$event->aggregateVersion,'payload'=>json_encode($event->payload,JSON_THROW_ON_ERROR),'actor_type'=>$actor->type->value,'actor_id'=>$actor->id,'origin'=>$event->context->origin->value,
            'correlation_id'=>$event->context->correlationId,'causation_id'=>$event->causationId,'state'=>'pending','publish_attempts'=>0,'occurred_at'=>$event->occurredAt,'created_at'=>now(),'updated_at'=>now()]);
    }
}

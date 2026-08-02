<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Infrastructure;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\DomainEventRecorderContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class DatabaseDomainEventRecorder implements DomainEventRecorderContract
{
    private const EVENT_TABLE = 'tz_domain_events';
    private const OUTBOX_TABLE = 'tz_outbox_messages';

    public function __construct(private ConnectionInterface $db) {}

    public function record(ActionRequest $request, ActionHandlerResult $result, string $correlationId, ?string $causationId): array
    {
        if ($result->aggregate === null) {
            return [];
        }

        $ids = [];
        foreach ($result->events as $event) {
            $eventId = (string) Str::ulid();
            $occurredAt = now();
            $envelope = [
                'event_id' => $eventId,
                'event_name' => $event->name,
                'event_version' => $event->version,
                'company_id' => $request->companyId,
                'actor_user_id' => $request->actorId,
                'aggregate_type' => $result->aggregate->type,
                'aggregate_public_id' => $result->aggregate->id,
                'correlation_id' => $correlationId,
                'causation_id' => $causationId,
                'payload' => $event->payload,
                'occurred_at' => $occurredAt->toISOString(),
                'consumers' => (array) config('workcore.outbox.consumers', []),
            ];

            $this->db->table(self::EVENT_TABLE)->insert([
                'event_id' => $eventId,
                'event_name' => $event->name,
                'event_version' => $event->version,
                'company_id' => $request->companyId,
                'actor_user_id' => $request->actorId,
                'aggregate_type' => $result->aggregate->type,
                'aggregate_public_id' => $result->aggregate->id,
                'correlation_id' => $correlationId,
                'causation_id' => $causationId,
                'payload_json' => json_encode($event->payload, JSON_THROW_ON_ERROR),
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);

            if ((bool) config('workcore.outbox.enabled', true)) {
                $this->db->table(self::OUTBOX_TABLE)->insert([
                    'message_id' => (string) Str::ulid(),
                    'company_id' => $request->companyId,
                    'topic' => 'workcore.domain_event',
                    'event_id' => $eventId,
                    'correlation_id' => $correlationId,
                    'causation_id' => $causationId,
                    'payload_json' => json_encode($envelope, JSON_THROW_ON_ERROR),
                    'status' => 'pending',
                    'attempts' => 0,
                    'available_at' => $occurredAt,
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);
            }
            $ids[] = $eventId;
        }

        return $ids;
    }
}

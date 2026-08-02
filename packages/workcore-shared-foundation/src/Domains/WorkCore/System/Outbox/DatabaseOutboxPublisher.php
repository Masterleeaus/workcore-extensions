<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Outbox;

use Illuminate\Database\ConnectionInterface;
use Throwable;

final class DatabaseOutboxPublisher implements OutboxPublisherContract
{
    private const TABLE = 'tz_outbox_messages';

    public function __construct(
        private ConnectionInterface $db,
        private OutboxTransportContract $transport,
    ) {}

    public function publishPending(int $limit = 100): array
    {
        $limit = max(1, min($limit, 1000));
        $this->recoverStaleClaims();

        $ids = $this->db->table(self::TABLE)
            ->whereIn('status', ['pending', 'retry'])
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        $counts = ['published' => 0, 'retried' => 0, 'failed' => 0];
        foreach ($ids as $id) {
            $message = $this->claim((int) $id);
            if ($message === null) {
                continue;
            }

            try {
                $this->transport->publish($message);
                $this->db->table(self::TABLE)->where('message_id', $message->messageId)->update([
                    'status' => 'published',
                    'published_at' => now(),
                    'locked_at' => null,
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
                $counts['published']++;
            } catch (Throwable $exception) {
                $attempts = $message->attempts + 1;
                $maxAttempts = max(1, (int) config('workcore.outbox.max_attempts', 10));
                $failed = $attempts >= $maxAttempts;
                $baseDelay = max(1, (int) config('workcore.outbox.retry_seconds', 60));
                $maxDelay = max($baseDelay, (int) config('workcore.outbox.max_retry_seconds', 3600));
                $delay = min($maxDelay, $baseDelay * (2 ** min($attempts - 1, 10)));
                $jitter = random_int(0, max(1, (int) floor($delay * 0.15)));
                $delay += $jitter;
                $this->db->table(self::TABLE)->where('message_id', $message->messageId)->update([
                    'status' => $failed ? 'failed' : 'retry',
                    'attempts' => $attempts,
                    'available_at' => now()->addSeconds($delay),
                    'locked_at' => null,
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                    'updated_at' => now(),
                ]);
                $counts[$failed ? 'failed' : 'retried']++;
            }
        }

        return $counts;
    }

    private function recoverStaleClaims(): void
    {
        $timeout = max(30, (int) config('workcore.outbox.processing_timeout_seconds', 300));
        $this->db->table(self::TABLE)
            ->where('status', 'processing')
            ->whereNotNull('locked_at')
            ->where('locked_at', '<=', now()->subSeconds($timeout))
            ->update([
                'status' => 'retry',
                'available_at' => now(),
                'locked_at' => null,
                'last_error' => 'Recovered stale processing claim.',
                'updated_at' => now(),
            ]);
    }

    private function claim(int $id): ?OutboxMessage
    {
        return $this->db->transaction(function () use ($id): ?OutboxMessage {
            $row = $this->db->table(self::TABLE)
                ->where('id', $id)
                ->whereIn('status', ['pending', 'retry'])
                ->where('available_at', '<=', now())
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return null;
            }

            $this->db->table(self::TABLE)->where('id', $id)->update([
                'status' => 'processing',
                'locked_at' => now(),
                'updated_at' => now(),
            ]);

            return new OutboxMessage(
                messageId: (string) $row->message_id,
                companyId: $row->company_id === null ? null : (int) $row->company_id,
                topic: (string) $row->topic,
                eventId: $row->event_id === null ? null : (string) $row->event_id,
                correlationId: (string) $row->correlation_id,
                causationId: $row->causation_id === null ? null : (string) $row->causation_id,
                payload: (array) json_decode((string) $row->payload_json, true, 512, JSON_THROW_ON_ERROR),
                attempts: (int) $row->attempts,
            );
        }, 3);
    }
}

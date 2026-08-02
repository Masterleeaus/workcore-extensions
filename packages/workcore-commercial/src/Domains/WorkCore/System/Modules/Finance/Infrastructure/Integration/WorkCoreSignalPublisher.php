<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Integration;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\SignalPublisher;
use App\Domains\WorkCore\System\Outbox\OutboxMessage;
use App\Domains\WorkCore\System\Outbox\OutboxPublisherContract;
use DateTimeImmutable;

/**
 * Pass 35 Stage B — Finance domain events published to WorkCore outbox.
 *
 * When Finance publishes a signal (e.g., "invoice.issued", "payment.confirmed"),
 * this bridge wraps it as a WorkCore OutboxMessage and publishes it through the
 * canonical outbox system for cross-domain event relay.
 *
 * Signals are fire-and-forget; if the outbox is unavailable, the operation
 * is permitted to fail (Finance's transactional boundary has already passed).
 */
final class WorkCoreSignalPublisher implements SignalPublisher
{
    public function __construct(
        private readonly OutboxPublisherContract $outbox,
    ) {}

    public function publish(string $signal, array $payload): void
    {
        $parts = explode('.', $signal, 2);
        if (count($parts) !== 2) {
            return; // Ignore malformed signals.
        }

        [$aggregate, $event] = $parts;

        $message = new OutboxMessage(
            aggregate_type: 'finance:' . $aggregate,
            aggregate_id: (string) ($payload['aggregate_id'] ?? $payload['id'] ?? 'unknown'),
            event_type: 'finance.' . $event,
            payload: $payload,
            occurred_at: new DateTimeImmutable(),
        );

        try {
            $this->outbox->publishImmediate($message);
        } catch (\Throwable) {
            // Finance has already committed; do not fail the transaction.
        }
    }
}

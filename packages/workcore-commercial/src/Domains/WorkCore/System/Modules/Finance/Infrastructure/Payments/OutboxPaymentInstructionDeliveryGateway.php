<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Payments;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\OutboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentInstructionDeliveryGateway;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Events\OutboxEvent;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentInstructions;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\QrArtifact;
use InvalidArgumentException;

final class OutboxPaymentInstructionDeliveryGateway implements PaymentInstructionDeliveryGateway
{
    public function __construct(
        private readonly OutboxRepository $outbox,
        private readonly IdentifierGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    public function deliver(PaymentInstructions $instructions, array $channels, ActionContext $context, ?QrArtifact $qrArtifact = null): array
    {
        $allowed = ['email', 'sms', 'whatsapp', 'messenger', 'chatbot', 'portal', 'print'];
        $results = [];
        foreach (array_values(array_unique($channels)) as $channel) {
            if (! is_string($channel) || ! in_array($channel, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported payment instruction delivery channel.');
            }
            $event = new OutboxEvent(
                $this->ids->uuid(),
                $context->companyId,
                'talk.money.payment_instructions.send_requested',
                'payment_request',
                $instructions->paymentRequestId,
                [
                    'channel' => $channel,
                    'instructions' => $instructions->toArray(),
                    'qr_artifact' => $qrArtifact?->toArray(),
                ],
                $this->clock->now(),
                $context,
            );
            $this->outbox->append($event);
            $results[] = ['channel' => $channel, 'state' => 'queued', 'outbox_event_id' => $event->id];
        }
        return $results;
    }
}

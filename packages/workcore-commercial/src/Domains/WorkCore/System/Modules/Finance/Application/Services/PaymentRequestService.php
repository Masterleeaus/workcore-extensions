<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Services;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ArtifactStore;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentInstructionDeliveryGateway;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentMethodConfigurationRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentRequestRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\QrArtifactRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\QrCodeRenderer;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodKey;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentQrPayloadBuilder;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentRequestRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\QrArtifact;
use App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Payments\PaymentAdapterRegistry;

final class PaymentRequestService
{
    public function __construct(
        private readonly PaymentRequestRepository $requests,
        private readonly PaymentMethodConfigurationRepository $configurations,
        private readonly PaymentAdapterRegistry $adapters,
        private readonly PaymentQrPayloadBuilder $payloadBuilder,
        private readonly QrCodeRenderer $qrRenderer,
        private readonly ArtifactStore $artifacts,
        private readonly QrArtifactRepository $qrArtifacts,
        private readonly PaymentInstructionDeliveryGateway $delivery,
        private readonly IdentifierGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    /** @param list<string> $channels @return array<string,mixed> */
    public function createAndDeliver(
        InvoiceRecord $invoice,
        PaymentMethodKey $method,
        array $channels,
        ActionContext $context,
        ?string $portalUrl = null,
    ): array {
        $existing = $this->requests->findOpenForInvoice($context->companyId, $invoice->id());
        $request = $existing ?? $this->requests->create(new PaymentRequestRecord(
            id: $this->ids->uuid(),
            companyId: $context->companyId,
            customerId: $invoice->draft->customerId,
            invoiceId: $invoice->id(),
            amount: $invoice->draft->total,
            reference: $invoice->draft->invoiceNumber,
            preferredMethod: $method,
            createdAt: $this->clock->now(),
            expiresAt: $invoice->draft->dueDate->modify('+30 days')->setTime(23, 59, 59),
        ), $context);

        $configuration = $this->configurations->configuration($context->companyId, $method);
        $instructions = $this->adapters->get($method)->createInstructions($request, $configuration, $portalUrl);
        $payload = $this->payloadBuilder->build($instructions);
        $svg = $this->qrRenderer->render($payload);
        $documentId = $this->artifacts->store(
            $context->companyId,
            'payment-qr',
            'payment-' . $request->id . '.svg',
            'image/svg+xml',
            $svg,
        );
        $qr = new QrArtifact(
            id: $this->ids->uuid(),
            companyId: $context->companyId,
            paymentRequestId: $request->id,
            documentId: $documentId,
            contentType: 'image/svg+xml',
            payloadHash: hash('sha256', $payload->content),
            createdAt: $this->clock->now(),
            expiresAt: $request->expiresAt,
        );
        $this->qrArtifacts->store($qr);
        $deliveries = $this->delivery->deliver($instructions, $channels, $context, $qr);

        return [
            'payment_request' => $request->toArray(),
            'instructions' => $instructions->toArray(),
            'qr_artifact' => $qr->toArray(),
            'deliveries' => $deliveries,
        ];
    }
}

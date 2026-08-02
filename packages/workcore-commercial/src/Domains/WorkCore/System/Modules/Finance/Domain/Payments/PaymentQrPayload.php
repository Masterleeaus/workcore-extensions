<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Payments;

final readonly class PaymentQrPayload
{
    public function __construct(public string $paymentRequestId, public string $content, public string $contentType='text/plain') {}
    public function toArray(): array { return ['payment_request_id'=>$this->paymentRequestId,'content'=>$this->content,'content_type'=>$this->contentType]; }
}

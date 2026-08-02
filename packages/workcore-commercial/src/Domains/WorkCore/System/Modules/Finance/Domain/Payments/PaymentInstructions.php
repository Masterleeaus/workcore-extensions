<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Payments;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;

final readonly class PaymentInstructions
{
    public function __construct(public string $paymentRequestId, public PaymentMethodKey $method, public Money $amount, public string $reference, public array $details, public ?string $portalUrl=null) {}
    public function toArray(): array { return ['payment_request_id'=>$this->paymentRequestId,'method'=>$this->method->value,'amount'=>$this->amount->toArray(),'reference'=>$this->reference,'details'=>$this->details,'portal_url'=>$this->portalUrl]; }
}

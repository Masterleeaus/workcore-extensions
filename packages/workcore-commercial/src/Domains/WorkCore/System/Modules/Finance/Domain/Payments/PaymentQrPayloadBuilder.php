<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Payments;

use App\Domains\WorkCore\System\Modules\Finance\Support\CanonicalJson;

final class PaymentQrPayloadBuilder
{
    public function build(PaymentInstructions $instructions): PaymentQrPayload
    {
        if ($instructions->portalUrl) return new PaymentQrPayload($instructions->paymentRequestId,$instructions->portalUrl,'text/uri-list');
        return new PaymentQrPayload($instructions->paymentRequestId,CanonicalJson::encode($instructions->toArray()),'application/json');
    }
}

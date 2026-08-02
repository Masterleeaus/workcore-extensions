<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\QrCodeRenderer;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentQrPayload;

final class InMemoryQrCodeRenderer implements QrCodeRenderer
{
    public function render(PaymentQrPayload $payload): string
    {
        return '<svg data-payment-request="' . htmlspecialchars($payload->paymentRequestId, ENT_QUOTES) . '"><text>' . htmlspecialchars($payload->content, ENT_QUOTES) . '</text></svg>';
    }
}

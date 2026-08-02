<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Payments;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\CustomerPaymentAdapter;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentInstructions;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodConfiguration;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodKey;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentRequestRecord;

final class PayPalPaymentAdapter implements CustomerPaymentAdapter
{
    public function key(): PaymentMethodKey { return PaymentMethodKey::PayPalCard; }

    public function createInstructions(PaymentRequestRecord $request, PaymentMethodConfiguration $configuration, ?string $portalUrl = null): PaymentInstructions
    {
        $url = $portalUrl ?? $configuration->requireString('payment_url');
        return new PaymentInstructions($request->id, $this->key(), $request->amount, $request->reference, [
            'payment_url' => $url,
            'reference' => $request->reference,
        ], $url);
    }
}

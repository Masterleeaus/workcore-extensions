<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Payments;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\CustomerPaymentAdapter;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentInstructions;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodConfiguration;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodKey;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentRequestRecord;

final class CashPaymentAdapter implements CustomerPaymentAdapter
{
    public function key(): PaymentMethodKey { return PaymentMethodKey::Cash; }

    public function createInstructions(PaymentRequestRecord $request, PaymentMethodConfiguration $configuration, ?string $portalUrl = null): PaymentInstructions
    {
        return new PaymentInstructions($request->id, $this->key(), $request->amount, $request->reference, [
            'instruction' => $configuration->values['instruction'] ?? 'Pay the field worker and request a Titan Money receipt.',
        ], $portalUrl);
    }
}

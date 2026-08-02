<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Payments;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\CustomerPaymentAdapter;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentInstructions;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodConfiguration;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodKey;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentRequestRecord;

final class BankTransferPaymentAdapter implements CustomerPaymentAdapter
{
    public function key(): PaymentMethodKey { return PaymentMethodKey::BankTransfer; }

    public function createInstructions(PaymentRequestRecord $request, PaymentMethodConfiguration $configuration, ?string $portalUrl = null): PaymentInstructions
    {
        return new PaymentInstructions($request->id, $this->key(), $request->amount, $request->reference, [
            'bsb' => $configuration->requireString('bsb'),
            'account_number' => $configuration->requireString('account_number'),
            'account_name' => $configuration->requireString('account_name'),
            'reference' => $request->reference,
        ], $portalUrl);
    }
}

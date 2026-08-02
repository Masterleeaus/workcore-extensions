<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentInstructions;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodConfiguration;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodKey;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentRequestRecord;

interface CustomerPaymentAdapter
{
    public function key(): PaymentMethodKey;

    public function createInstructions(
        PaymentRequestRecord $request,
        PaymentMethodConfiguration $configuration,
        ?string $portalUrl = null,
    ): PaymentInstructions;
}

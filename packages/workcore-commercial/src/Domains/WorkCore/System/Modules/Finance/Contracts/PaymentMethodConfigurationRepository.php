<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodConfiguration;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodKey;

interface PaymentMethodConfigurationRepository
{
    public function configuration(string $companyId, PaymentMethodKey $method): PaymentMethodConfiguration;
}

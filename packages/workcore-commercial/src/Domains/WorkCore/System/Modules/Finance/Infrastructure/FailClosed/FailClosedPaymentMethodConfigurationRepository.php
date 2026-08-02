<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentMethodConfigurationRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodConfiguration;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodKey;
use RuntimeException;

final class FailClosedPaymentMethodConfigurationRepository implements PaymentMethodConfigurationRepository
{
    public function configuration(string $companyId,PaymentMethodKey $method): PaymentMethodConfiguration { throw new RuntimeException("Configure {$method->value} payment instructions for the active company before delivery."); }
}

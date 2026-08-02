<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentMethodConfigurationRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodConfiguration;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodKey;

final class InMemoryPaymentMethodConfigurationRepository implements PaymentMethodConfigurationRepository
{
    /** @param array<string,array<string,mixed>> $values */
    public function __construct(private readonly array $values) {}
    public function configuration(string $companyId, PaymentMethodKey $method): PaymentMethodConfiguration
    {
        return new PaymentMethodConfiguration($method, $this->values[$method->value] ?? []);
    }
}

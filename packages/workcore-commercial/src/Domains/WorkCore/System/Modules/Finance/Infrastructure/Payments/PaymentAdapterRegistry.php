<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Payments;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\CustomerPaymentAdapter;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodKey;
use InvalidArgumentException;

final class PaymentAdapterRegistry
{
    /** @var array<string,CustomerPaymentAdapter> */
    private array $adapters = [];

    /** @param iterable<CustomerPaymentAdapter> $adapters */
    public function __construct(iterable $adapters = [])
    {
        foreach ($adapters as $adapter) {
            $this->register($adapter);
        }
    }

    public function register(CustomerPaymentAdapter $adapter): void
    {
        $key = $adapter->key()->value;
        if (isset($this->adapters[$key])) {
            throw new InvalidArgumentException("Payment adapter {$key} is already registered.");
        }
        $this->adapters[$key] = $adapter;
    }

    public function get(PaymentMethodKey $key): CustomerPaymentAdapter
    {
        return $this->adapters[$key->value]
            ?? throw new InvalidArgumentException("Payment adapter {$key->value} is not registered.");
    }
}

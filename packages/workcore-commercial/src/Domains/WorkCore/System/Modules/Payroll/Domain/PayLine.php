<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Payroll\Domain;

use InvalidArgumentException;

final readonly class PayLine
{
    public function __construct(public string $payCode, public int $amountMinor)
    {
        if (trim($payCode) === '') throw new InvalidArgumentException('Pay code is required.');
    }
}

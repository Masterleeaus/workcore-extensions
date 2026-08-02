<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Tax;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;

final readonly class GstBreakdown
{
    public function __construct(public Money $subtotal, public Money $tax, public Money $total, public int $rateBasisPoints) {}

    public function toArray(): array
    {
        return ['subtotal'=>$this->subtotal->toArray(),'tax'=>$this->tax->toArray(),'total'=>$this->total->toArray(),'rate_basis_points'=>$this->rateBasisPoints];
    }
}

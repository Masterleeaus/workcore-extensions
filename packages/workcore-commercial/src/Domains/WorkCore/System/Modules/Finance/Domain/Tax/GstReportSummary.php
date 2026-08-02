<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Tax;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;

final readonly class GstReportSummary
{
    public function __construct(public Money $gstCollected, public Money $gstCredits, public Money $netPayable) {}
    public function toArray(): array { return ['gst_collected'=>$this->gstCollected->toArray(),'gst_credits'=>$this->gstCredits->toArray(),'net_payable'=>$this->netPayable->toArray()]; }
}

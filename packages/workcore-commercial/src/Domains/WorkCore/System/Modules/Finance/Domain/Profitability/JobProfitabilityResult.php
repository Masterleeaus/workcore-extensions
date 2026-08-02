<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Profitability;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;

final readonly class JobProfitabilityResult
{
    public function __construct(public string $jobId, public Money $revenue, public Money $labourCost, public Money $materialCost, public Money $otherCost, public Money $totalCost, public Money $profit, public ?int $marginBasisPoints) {}
    public function toArray(): array { return ['job_id'=>$this->jobId,'revenue'=>$this->revenue->toArray(),'labour_cost'=>$this->labourCost->toArray(),'material_cost'=>$this->materialCost->toArray(),'other_cost'=>$this->otherCost->toArray(),'total_cost'=>$this->totalCost->toArray(),'profit'=>$this->profit->toArray(),'margin_basis_points'=>$this->marginBasisPoints]; }
}

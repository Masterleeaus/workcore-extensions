<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Ageing;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\CurrencyCode;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;

final readonly class AgeingReport
{
    /** @param array<string,int> $bucketMinorAmounts */
    public function __construct(public CurrencyCode $currency, public array $bucketMinorAmounts, public int $recordCount) {}

    public function amountFor(AgeingBucket $bucket): Money
    {
        return Money::ofMinor($this->bucketMinorAmounts[$bucket->value] ?? 0, $this->currency);
    }

    public function toArray(): array
    {
        $buckets=[];
        foreach (AgeingBucket::cases() as $bucket) $buckets[$bucket->value]=$this->amountFor($bucket)->toArray();
        return ['currency'=>$this->currency->value,'record_count'=>$this->recordCount,'buckets'=>$buckets];
    }
}

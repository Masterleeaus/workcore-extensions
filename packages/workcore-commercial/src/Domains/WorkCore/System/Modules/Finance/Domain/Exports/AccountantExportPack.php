<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Exports;

use DateTimeImmutable;

final readonly class AccountantExportPack
{
    /** @param array<string,mixed> $sections */
    public function __construct(public string $companyId, public string $currencyCode, public DateTimeImmutable $generatedAt, public array $sections) {}

    public static function empty(string $companyId, string $currencyCode, DateTimeImmutable $generatedAt): self
    {
        return new self($companyId,$currencyCode,$generatedAt,[
            'journals'=>[], 'trial_balance'=>[], 'gst_summary'=>[], 'receivables_ageing'=>[], 'payables_ageing'=>[],
            'bank_reconciliation'=>[], 'cashflow_forecast'=>[], 'job_profitability'=>[],
        ]);
    }

    public function toArray(): array { return ['company_id'=>$this->companyId,'currency_code'=>$this->currencyCode,'generated_at'=>$this->generatedAt->format(DATE_ATOM),'sections'=>$this->sections]; }
}

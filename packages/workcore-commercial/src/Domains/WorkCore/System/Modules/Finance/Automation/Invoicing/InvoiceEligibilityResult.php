<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation\Invoicing;

final readonly class InvoiceEligibilityResult
{
    /** @param list<string> $reasons */
    public function __construct(public bool $eligible, public array $reasons) {}
    public function toArray(): array { return ['eligible'=>$this->eligible,'reasons'=>$this->reasons]; }
}

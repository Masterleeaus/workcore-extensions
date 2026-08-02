<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation\Invoicing;

final readonly class InvoiceEligibilityInput
{
    public function __construct(public string $jobId, public bool $completed, public bool $billable, public bool $existingInvoice, public bool $customerAvailable, public int $billableLines, public bool $evidenceComplete) {}
}

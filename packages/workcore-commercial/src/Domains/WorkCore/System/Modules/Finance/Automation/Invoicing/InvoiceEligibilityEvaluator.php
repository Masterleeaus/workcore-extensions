<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation\Invoicing;

final class InvoiceEligibilityEvaluator
{
    public function evaluate(InvoiceEligibilityInput $input): InvoiceEligibilityResult
    {
        $reasons=[];
        if (!$input->completed) $reasons[]='job_not_completed';
        if (!$input->billable) $reasons[]='job_not_billable';
        if ($input->existingInvoice) $reasons[]='invoice_already_exists';
        if (!$input->customerAvailable) $reasons[]='customer_missing';
        if ($input->billableLines<1) $reasons[]='billable_lines_missing';
        if (!$input->evidenceComplete) $reasons[]='required_evidence_incomplete';
        return new InvoiceEligibilityResult($reasons===[],$reasons);
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentEvidenceRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Receivables\ReceivableAccount;

interface PaymentRepository
{
    public function findByEvidence(string $companyId, string $evidenceId): ?PaymentRecord;
    public function createConfirmed(PaymentEvidenceRecord $evidence, ReceivableAccount $receivable, int $allocationMinor, ActionContext $context): PaymentRecord;
}

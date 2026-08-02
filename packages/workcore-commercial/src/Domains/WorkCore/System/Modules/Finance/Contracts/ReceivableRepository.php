<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Receivables\ReceivableAccount;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation\OpenReceivableCandidate;

interface ReceivableRepository
{
    /** @return list<OpenReceivableCandidate> */
    public function openCandidates(string $companyId, string $currencyCode): array;
    public function get(string $companyId, string $invoiceId): ?ReceivableAccount;
    public function allocate(string $companyId, string $invoiceId, int $amountMinor, ActionContext $context): ReceivableAccount;
}

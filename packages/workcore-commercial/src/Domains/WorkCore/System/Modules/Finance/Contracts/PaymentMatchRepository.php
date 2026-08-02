<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation\PaymentMatchCandidate;

interface PaymentMatchRepository
{
    /** @param list<PaymentMatchCandidate> $candidates */
    public function saveRanked(string $companyId, string $evidenceId, array $candidates, int $autoThreshold, ActionContext $context): void;
}

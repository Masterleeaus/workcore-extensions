<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentMatchRepository;

final class InMemoryPaymentMatchRepository implements PaymentMatchRepository
{
    /** @var list<array<string,mixed>> */ public array $candidates=[];
    public function saveRanked(string $companyId,string $evidenceId,array $candidates,int $autoThreshold,ActionContext $context): void { foreach($candidates as $candidate)$this->candidates[]=['company_id'=>$companyId,'evidence_id'=>$evidenceId,'candidate'=>$candidate,'auto_threshold'=>$autoThreshold,'context'=>$context]; }
}

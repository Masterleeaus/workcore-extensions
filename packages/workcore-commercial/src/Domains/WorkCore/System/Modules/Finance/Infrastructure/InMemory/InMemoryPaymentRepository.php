<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentEvidenceRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Receivables\ReceivableAccount;

final class InMemoryPaymentRepository implements PaymentRepository
{
    /** @var array<string,PaymentRecord> */ public array $payments=[];
    public function findByEvidence(string $companyId,string $evidenceId): ?PaymentRecord { foreach($this->payments as $p)if($p->companyId===$companyId&&$p->evidenceId===$evidenceId)return $p; return null; }
    public function createConfirmed(PaymentEvidenceRecord $evidence,ReceivableAccount $receivable,int $allocationMinor,ActionContext $context): PaymentRecord { $id='payment-'.(count($this->payments)+1); $unallocated=$evidence->amount->minorAmount-$allocationMinor; $state=$unallocated===0?'allocated':'partially_allocated'; return $this->payments[$id]=new PaymentRecord($id,$evidence->companyId,$receivable->customerId,$evidence->paymentMethod,$state,$evidence->amount,$allocationMinor,$unallocated,$evidence->referenceText,$evidence->observedAt,$evidence->id); }
}

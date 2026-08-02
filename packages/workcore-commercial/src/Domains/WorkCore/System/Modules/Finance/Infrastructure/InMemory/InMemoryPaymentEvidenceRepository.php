<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentEvidenceRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentEvidenceRecord;

final class InMemoryPaymentEvidenceRepository implements PaymentEvidenceRepository
{
    /** @var array<string,PaymentEvidenceRecord> */ public array $records=[];
    public function findById(string $companyId,string $evidenceId): ?PaymentEvidenceRecord { $r=$this->records[$evidenceId]??null; return $r?->companyId===$companyId?$r:null; }
    public function findByHash(string $companyId,string $rawPayloadHash): ?PaymentEvidenceRecord { foreach($this->records as $record) if($record->companyId===$companyId&&$record->rawPayloadHash===$rawPayloadHash)return $record; return null; }
    public function save(PaymentEvidenceRecord $record,ActionContext $context): PaymentEvidenceRecord { return $this->records[$record->id]=$record; }
    public function markVerified(string $companyId,string $evidenceId,int $confidenceScore,string $status): void { $r=$this->records[$evidenceId]??null; if($r===null||$r->companyId!==$companyId)return; $this->records[$evidenceId]=new PaymentEvidenceRecord($r->id,$r->companyId,$r->sourceType,$r->amount,$r->referenceText,$r->payerHint,$r->rawPayloadHash,$r->observedAt,$r->sourceDeviceId,$r->duplicateStatus,$status,$r->paymentMethod); }
}

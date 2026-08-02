<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentEvidenceRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentEvidenceRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class DatabasePaymentEvidenceRepository implements PaymentEvidenceRepository
{
    public function findById(string $companyId,string $evidenceId): ?PaymentEvidenceRecord {$row=DB::table('tm_payment_evidence')->where('company_id',$companyId)->where('id',$evidenceId)->first();return $row?$this->hydrate($row):null;}
    public function findByHash(string $companyId,string $rawPayloadHash): ?PaymentEvidenceRecord {$row=DB::table('tm_payment_evidence')->where('company_id',$companyId)->where('raw_payload_hash',$rawPayloadHash)->first();return $row?$this->hydrate($row):null;}
    public function save(PaymentEvidenceRecord $record,ActionContext $context): PaymentEvidenceRecord {DB::table('tm_payment_evidence')->insert(['id'=>$record->id,'company_id'=>$record->companyId,'payment_claim_id'=>null,'payment_id'=>null,'source_type'=>$record->sourceType,'payment_method'=>$record->paymentMethod,'source_device_id'=>$record->sourceDeviceId,'observed_at'=>$record->observedAt,'amount_minor'=>$record->amount->minorAmount,'currency_code'=>$record->amount->currency->value,'reference_text'=>$record->referenceText,'payer_hint'=>$record->payerHint,'raw_payload_hash'=>$record->rawPayloadHash,'confidence_score'=>0,'duplicate_status'=>$record->duplicateStatus,'verification_status'=>$record->verificationStatus,'retention_expires_at'=>$record->observedAt->modify('+90 days'),'created_at'=>now(),'updated_at'=>now()]);return $record;}
    public function markVerified(string $companyId,string $evidenceId,int $confidenceScore,string $status): void {DB::table('tm_payment_evidence')->where('company_id',$companyId)->where('id',$evidenceId)->update(['confidence_score'=>$confidenceScore,'verification_status'=>$status,'updated_at'=>now()]);}
    private function hydrate(object $r): PaymentEvidenceRecord{return new PaymentEvidenceRecord((string)$r->id,(string)$r->company_id,(string)$r->source_type,Money::ofMinor((int)$r->amount_minor,(string)$r->currency_code),(string)($r->reference_text??''),$r->payer_hint,(string)$r->raw_payload_hash,new DateTimeImmutable((string)$r->observed_at),$r->source_device_id,(string)$r->duplicate_status,(string)$r->verification_status,(string)($r->payment_method??'bank_transfer'));}
}

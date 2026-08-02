<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentEvidenceRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Receivables\ReceivableAccount;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabasePaymentRepository implements PaymentRepository
{
    public function __construct(private readonly IdentifierGenerator $ids){}
    public function findByEvidence(string $companyId,string $evidenceId): ?PaymentRecord {$r=DB::table('tm_payments')->where('company_id',$companyId)->where('external_reference','evidence:'.$evidenceId)->first();return $r?$this->hydrate($r,$evidenceId):null;}
    public function createConfirmed(PaymentEvidenceRecord $evidence,ReceivableAccount $receivable,int $allocationMinor,ActionContext $context): PaymentRecord {$actor=$context->actor??throw new RuntimeException('Audit actor required.');$id=$this->ids->uuid();$unallocated=$evidence->amount->minorAmount-$allocationMinor;$state=$unallocated===0?'allocated':'partially_allocated';$now=now();DB::table('tm_payments')->insert(['id'=>$id,'company_id'=>$context->companyId,'workcore_customer_id'=>$receivable->customerId,'payment_request_id'=>null,'method'=>$evidence->paymentMethod,'state'=>$state,'amount_minor'=>$evidence->amount->minorAmount,'allocated_minor'=>$allocationMinor,'refunded_minor'=>0,'currency_code'=>$evidence->amount->currency->value,'external_reference'=>'evidence:'.$evidence->id,'reported_at'=>$evidence->observedAt,'confirmed_at'=>$evidence->observedAt,'settled_at'=>null,'reversed_at'=>null,'created_by_user_id'=>$context->userId,'updated_by_user_id'=>$context->userId,'created_by_actor_type'=>$actor->type->value,'created_by_actor_id'=>$actor->id,'updated_by_actor_type'=>$actor->type->value,'updated_by_actor_id'=>$actor->id,'origin'=>$context->origin->value,'correlation_id'=>$context->correlationId,'created_at'=>$now,'updated_at'=>$now]);DB::table('tm_payment_allocations')->insert(['id'=>$this->ids->uuid(),'company_id'=>$context->companyId,'payment_id'=>$id,'target_type'=>'invoice','target_id'=>$receivable->invoiceId,'amount_minor'=>$allocationMinor,'currency_code'=>$evidence->amount->currency->value,'allocated_by_actor_type'=>$actor->type->value,'allocated_by_actor_id'=>$actor->id,'approval_id'=>$context->approvalId,'correlation_id'=>$context->correlationId,'allocated_at'=>$evidence->observedAt,'created_at'=>$now,'updated_at'=>$now]);DB::table('tm_payment_evidence')->where('company_id',$context->companyId)->where('id',$evidence->id)->update(['payment_id'=>$id,'updated_at'=>$now]);return new PaymentRecord($id,$context->companyId,$receivable->customerId,$evidence->paymentMethod,$state,$evidence->amount,$allocationMinor,$unallocated,$evidence->referenceText,$evidence->observedAt,$evidence->id);}
    private function hydrate(object $r,string $evidenceId): PaymentRecord{return new PaymentRecord((string)$r->id,(string)$r->company_id,$r->workcore_customer_id,(string)$r->method,(string)$r->state,Money::ofMinor((int)$r->amount_minor,(string)$r->currency_code),(int)$r->allocated_minor,(int)$r->amount_minor-(int)$r->allocated_minor,$r->external_reference,new DateTimeImmutable((string)$r->confirmed_at),$evidenceId);}
}

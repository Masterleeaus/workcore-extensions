<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentRequestRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentMethodKey;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentRequestRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabasePaymentRequestRepository implements PaymentRequestRepository
{
    public function findOpenForInvoice(string $companyId,string $invoiceId): ?PaymentRequestRecord
    {
        $r=DB::table('tm_payment_requests')->where('company_id',$companyId)->where('invoice_id',$invoiceId)->where('state','open')->first();
        return $r?$this->hydrate($r):null;
    }
    public function create(PaymentRequestRecord $request,ActionContext $context): PaymentRequestRecord
    {
        $actor=$context->actor??throw new RuntimeException('Audit actor required.'); $now=now();
        DB::table('tm_payment_requests')->insert(['id'=>$request->id,'company_id'=>$request->companyId,'workcore_customer_id'=>$request->customerId,'invoice_id'=>$request->invoiceId,'purpose'=>'invoice','state'=>$request->state,
            'preferred_method'=>$request->preferredMethod->value,'amount_minor'=>$request->amount->minorAmount,'received_minor'=>0,'currency_code'=>$request->amount->currency->value,'payment_reference'=>$request->reference,
            'expires_at'=>$request->expiresAt,'created_by_user_id'=>$context->userId,'updated_by_user_id'=>$context->userId,'created_by_actor_type'=>$actor->type->value,'created_by_actor_id'=>$actor->id,
            'updated_by_actor_type'=>$actor->type->value,'updated_by_actor_id'=>$actor->id,'origin'=>$context->origin->value,'correlation_id'=>$context->correlationId,'created_at'=>$now,'updated_at'=>$now]); return $request;
    }
    private function hydrate(object $r): PaymentRequestRecord { return new PaymentRequestRecord($r->id,$r->company_id,$r->workcore_customer_id,$r->invoice_id,Money::ofMinor((int)$r->amount_minor,$r->currency_code),$r->payment_reference,PaymentMethodKey::from($r->preferred_method),new DateTimeImmutable((string)$r->created_at),$r->expires_at?new DateTimeImmutable((string)$r->expires_at):null,$r->state); }
}

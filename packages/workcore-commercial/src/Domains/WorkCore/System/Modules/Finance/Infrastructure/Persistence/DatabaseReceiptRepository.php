<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ReceiptRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\ReceiptRecord;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabaseReceiptRepository implements ReceiptRepository
{
    public function create(ReceiptRecord $receipt,ActionContext $context): ReceiptRecord {$actor=$context->actor??throw new RuntimeException('Audit actor required.');DB::table('tm_receipts')->insertOrIgnore(['id'=>$receipt->id,'company_id'=>$receipt->companyId,'payment_id'=>$receipt->paymentId,'invoice_id'=>$receipt->invoiceId,'receipt_number'=>$receipt->receiptNumber,'state'=>$receipt->state,'amount_minor'=>$receipt->amount->minorAmount,'currency_code'=>$receipt->amount->currency->value,'document_id'=>null,'issued_at'=>$receipt->issuedAt,'delivered_at'=>null,'created_by_actor_type'=>$actor->type->value,'created_by_actor_id'=>$actor->id,'origin'=>$context->origin->value,'correlation_id'=>$context->correlationId,'created_at'=>now(),'updated_at'=>now()]);return $receipt;}
}

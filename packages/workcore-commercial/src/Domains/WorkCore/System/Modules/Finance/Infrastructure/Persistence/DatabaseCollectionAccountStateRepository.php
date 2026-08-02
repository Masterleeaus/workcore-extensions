<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Automation\Collections\CollectionAccountState;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\CollectionAccountStateRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class DatabaseCollectionAccountStateRepository implements CollectionAccountStateRepository
{
    public function get(string $companyId,string $invoiceId): ?CollectionAccountState
    {
        $invoice=DB::table('tm_invoices')->where('company_id',$companyId)->where('id',$invoiceId)->first();
        if($invoice===null)return null;
        $dispute=DB::table('tm_financial_exceptions')->where('company_id',$companyId)->where('entity_type','invoice')->where('entity_id',$invoiceId)->where('exception_type','invoice_dispute')->where('state','open')->exists();
        $plan=DB::table('tm_payment_plans')->where('company_id',$companyId)->where('invoice_id',$invoiceId)->whereIn('state',['accepted','active'])->exists();
        $claim=DB::table('tm_payment_claims as c')->join('tm_payment_requests as r','r.id','=','c.payment_request_id')->where('c.company_id',$companyId)->where('r.invoice_id',$invoiceId)->where('c.state','pending_verification')->exists();
        $control=DB::table('tm_collection_controls')->where('company_id',$companyId)->where('invoice_id',$invoiceId)->first();$paused=$control!==null&&(bool)$control->paused&&($control->paused_until===null||new DateTimeImmutable((string)$control->paused_until)>=new DateTimeImmutable('now'));
        $promise=DB::table('tm_payment_promises')->where('company_id',$companyId)->where('invoice_id',$invoiceId)->where('state','active')->orderByDesc('promised_date')->first();
        return new CollectionAccountState($invoiceId,(int)$invoice->balance_minor,(string)$invoice->currency_code,(int)$invoice->balance_minor===0||$invoice->state==='paid',$dispute,$plan,$claim,$paused,$invoice->state==='voided',$invoice->state==='credited',$promise?new DateTimeImmutable((string)$promise->promised_date.' 23:59:59'):null);
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ReceivableRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Receivables\ReceivableAccount;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation\OpenReceivableCandidate;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabaseReceivableRepository implements ReceivableRepository
{
    public function openCandidates(string $companyId,string $currencyCode): array {$rows=DB::table('tm_invoices')->where('company_id',$companyId)->where('currency_code',strtoupper($currencyCode))->where('balance_minor','>',0)->whereIn('state',['issued','delivered','viewed','partially_paid','overdue'])->get();$out=[];foreach($rows as $r)$out[]=new OpenReceivableCandidate((string)$r->id,Money::ofMinor((int)$r->balance_minor,(string)$r->currency_code),(string)($r->invoice_number??''),null,new DateTimeImmutable((string)($r->issued_at??$r->created_at)));return $out;}
    public function get(string $companyId,string $invoiceId): ?ReceivableAccount {$r=DB::table('tm_invoices')->where('company_id',$companyId)->where('id',$invoiceId)->first();return $r?new ReceivableAccount((string)$r->id,(string)$r->invoice_number,(string)$r->company_id,(string)$r->workcore_customer_id,Money::ofMinor((int)$r->balance_minor,(string)$r->currency_code),(string)$r->invoice_number,null,new DateTimeImmutable((string)($r->issued_at??$r->created_at)),(string)$r->state):null;}
    public function allocate(string $companyId,string $invoiceId,int $amountMinor,ActionContext $context): ReceivableAccount {$current=$this->get($companyId,$invoiceId)??throw new RuntimeException('Receivable not found.');if($amountMinor<=0||$amountMinor>$current->balance->minorAmount)throw new RuntimeException('Allocation exceeds receivable balance.');$balance=$current->balance->minorAmount-$amountMinor;$state=$balance===0?'paid':'partially_paid';$updated=DB::table('tm_invoices')->where('company_id',$companyId)->where('id',$invoiceId)->where('balance_minor','>=',$amountMinor)->update(['balance_minor'=>DB::raw('balance_minor - '.(int)$amountMinor),'state'=>$state,'updated_by_user_id'=>$context->userId,'updated_by_actor_type'=>$context->actor?->type->value,'updated_by_actor_id'=>$context->actor?->id,'origin'=>$context->origin->value,'correlation_id'=>$context->correlationId,'updated_at'=>now()]);if($updated!==1)throw new RuntimeException('Receivable allocation conflicted.');return $this->get($companyId,$invoiceId)??throw new RuntimeException('Updated receivable missing.');}
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ReconciliationRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation\ReconciliationSession;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabaseReconciliationRepository implements ReconciliationRepository
{
    public function get(string $companyId,string $reconciliationId):?ReconciliationSession{$row=DB::table('tm_reconciliations')->where('company_id',$companyId)->where('id',$reconciliationId)->first();if($row===null)return null;$unresolved=DB::table('tm_reconciliation_lines')->where('company_id',$companyId)->where('reconciliation_id',$reconciliationId)->whereNotIn('state',['confirmed','ignored'])->count();return new ReconciliationSession((string)$row->id,(string)$row->company_id,(string)$row->bank_account_id,(int)$row->statement_closing_balance_minor,(int)$row->reconciled_closing_balance_minor,(string)$row->currency_code,$unresolved,(string)$row->state);}
    public function close(string $companyId,string $reconciliationId,ActionContext $context,DateTimeImmutable $closedAt):void{$actor=$context->actor??throw new RuntimeException('Audit actor required.');DB::table('tm_reconciliations')->where('company_id',$companyId)->where('id',$reconciliationId)->where('state','open')->update(['state'=>'closed','closed_at'=>$closedAt,'closed_by_actor_type'=>$actor->type->value,'closed_by_actor_id'=>$actor->id,'updated_at'=>now()]);}
}

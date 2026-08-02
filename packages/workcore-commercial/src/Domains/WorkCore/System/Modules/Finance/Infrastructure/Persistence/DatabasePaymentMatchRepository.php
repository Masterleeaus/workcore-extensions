<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentMatchRepository;
use Illuminate\Support\Facades\DB;

final class DatabasePaymentMatchRepository implements PaymentMatchRepository
{
    public function __construct(private readonly IdentifierGenerator $ids){}
    public function saveRanked(string $companyId,string $evidenceId,array $candidates,int $autoThreshold,ActionContext $context): void
    {
        foreach($candidates as $candidate){
            $key=['payment_evidence_id'=>$evidenceId,'invoice_id'=>$candidate->invoiceId];
            $values=['company_id'=>$companyId,'confidence_score'=>$candidate->confidenceScore,'state'=>$candidate->status->value,'reasons'=>json_encode($candidate->reasons,JSON_THROW_ON_ERROR),'policy_may_auto_confirm'=>$candidate->policyMayAutoConfirm&&$candidate->confidenceScore>=$autoThreshold,'updated_at'=>now()];
            $existing=DB::table('tm_payment_match_candidates')->where($key)->first();
            if($existing===null)DB::table('tm_payment_match_candidates')->insert(['id'=>$this->ids->uuid()]+$key+$values+['created_at'=>now()]);
            else DB::table('tm_payment_match_candidates')->where('id',$existing->id)->where('company_id',$companyId)->update($values);
        }
    }
}

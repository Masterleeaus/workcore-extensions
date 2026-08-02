<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Actions;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionResult;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ExecuteCollectionFollowupInput;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\GenerativeUiEnvelope;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\ResultStatus;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\RiskAssessment;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ApprovalRequirement;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\ApprovalMode;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\RiskLevel;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\FinanceAccessGate;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\TitanMoneyPermission;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\SurfaceType;
use App\Domains\WorkCore\System\Modules\Finance\Automation\Collections\CollectionSuppressionEvaluator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionAttemptRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\CollectionAccountStateRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\CollectionMessageGateway;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdempotencyStore;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TitanMoneyAction;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TransactionManager;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\IdempotencyClaimStatus;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\IdempotencyKey;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\RequestFingerprint;
use RuntimeException;

final class ExecuteCollectionFollowup implements TitanMoneyAction
{
    public const ACTION_KEY='money.collection.execute_followup';
    public function __construct(private readonly FinanceAccessGate $access,private readonly CollectionSuppressionEvaluator $suppressions,private readonly CollectionAccountStateRepository $states,private readonly CollectionMessageGateway $messages,private readonly ActionAttemptRepository $attempts,private readonly IdempotencyStore $idempotency,private readonly Clock $clock,private readonly TransactionManager $transactions){}
    public function authorize(ActionContext $context): void { $this->access->assertAllowed($context,TitanMoneyPermission::CollectionsManage); }
    public function assessRisk(ActionInput $input,ActionContext $context): RiskAssessment
    {
        if(!$input instanceof ExecuteCollectionFollowupInput) throw new \InvalidArgumentException('ExecuteCollectionFollowupInput is required.');
        return new RiskAssessment($input->approvalRequired?RiskLevel::High:RiskLevel::Medium,['Sends a customer collection communication.'],$input->approvalRequired?new ApprovalRequirement(ApprovalMode::ExplicitApproval,true,'Send collection escalation',['invoice_id','step_code']):null);
    }
    public function execute(ActionInput $input,ActionContext $context): ActionResult
    {
        if(!$input instanceof ExecuteCollectionFollowupInput)throw new \InvalidArgumentException('ExecuteCollectionFollowupInput is required.');
        $this->authorize($context);
        $key=IdempotencyKey::fromRaw($context->idempotencyKey); $claim=$this->idempotency->claim($context->companyId,self::ACTION_KEY,$key,RequestFingerprint::fromPayload($input->toArray()));
        if($claim->status===IdempotencyClaimStatus::Replay)return $this->result($claim->resultPayload??[],'money.collection.followup_replayed');
        if($claim->status!==IdempotencyClaimStatus::Acquired)return new ActionResult(ResultStatus::Failed,'money.collection.idempotency_conflict',[],null,[['message'=>'Collection action is already processing or conflicts with the existing key.']],null);
        try{
            $data=$this->transactions->run(function()use($input,$context):array{
                $state=$this->states->get($context->companyId,$input->invoiceId)??throw new RuntimeException('Invoice collection state not found.');
                $decision=$this->suppressions->evaluate($state,$this->clock->now());
                if($decision->suppressed){$data=['invoice_id'=>$input->invoiceId,'step_code'=>$input->stepCode,'suppressed'=>true,'reason'=>$decision->code,'message_ids'=>[]];$this->attempts->record(self::ACTION_KEY,'suppressed',1,$data,$context);return $data;}
                if($input->approvalRequired&&$context->approvalId===null)throw new RuntimeException('This collection step requires approval.');
                $ids=$this->messages->queue($input->invoiceId,$input->stepCode,$input->channels,$input->tone,$state->balanceMinor,$state->currencyCode,$context);
                $data=['invoice_id'=>$input->invoiceId,'step_code'=>$input->stepCode,'suppressed'=>false,'reason'=>null,'message_ids'=>$ids];$this->attempts->record(self::ACTION_KEY,'queued',1,$data,$context);return $data;
            });
            $this->idempotency->complete($context->companyId,self::ACTION_KEY,$key,$data);
            return $this->result($data,($data['suppressed']??false)?'money.collection.suppressed':'money.collection.followup_queued');
        }catch(\Throwable $e){$this->idempotency->fail($context->companyId,self::ACTION_KEY,$key,'collection_followup_failed');$this->attempts->record(self::ACTION_KEY,'failed',1,['error'=>$e->getMessage()],$context,'collection_followup_failed');return new ActionResult(ResultStatus::Failed,'money.collection.followup_failed',[],null,[['message'=>$e->getMessage()]],null);}
    }
    /** @param array<string,mixed> $data */ private function result(array $data,string $code): ActionResult{return new ActionResult(ResultStatus::Success,$code,$data,new GenerativeUiEnvelope('1.0','money.collection-followup-card',$data,[SurfaceType::Workspace,SurfaceType::MobileCard,SurfaceType::Headless],[]),[],null);}
}

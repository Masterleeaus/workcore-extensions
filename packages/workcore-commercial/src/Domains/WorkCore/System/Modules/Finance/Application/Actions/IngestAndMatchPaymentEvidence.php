<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Actions;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionResult;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\GenerativeUiEnvelope;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\IngestPaymentEvidenceInput;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\ResultStatus;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\RiskAssessment;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\RiskLevel;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\SurfaceType;
use App\Domains\WorkCore\System\Modules\Finance\Application\Services\PaymentConfirmationService;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\FinanceAccessGate;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\TitanMoneyPermission;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AuditRecorder;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinancialExceptionRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdempotencyStore;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\OutboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentEvidenceRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentMatchRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ReceivableRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TitanMoneyAction;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TransactionManager;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Events\OutboxEvent;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentEvidenceRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation\BankDepositEvidence;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation\BankDepositMatcher;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\IdempotencyClaimStatus;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\IdempotencyKey;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\RequestFingerprint;

final class IngestAndMatchPaymentEvidence implements TitanMoneyAction
{
    public const ACTION_KEY='money.ingest_payment_evidence';
    public function __construct(private readonly FinanceAccessGate $access,private readonly PaymentEvidenceRepository $evidence,private readonly ReceivableRepository $receivables,private readonly PaymentMatchRepository $matches,private readonly BankDepositMatcher $matcher,private readonly PaymentConfirmationService $confirmation,private readonly FinancialExceptionRepository $exceptions,private readonly AuditRecorder $audit,private readonly OutboxRepository $outbox,private readonly IdempotencyStore $idempotency,private readonly Clock $clock,private readonly IdentifierGenerator $ids,private readonly TransactionManager $transactions,private readonly int $autoConfirmThreshold=95){}
    public function authorize(ActionContext $context): void { $this->access->assertAllowed($context,TitanMoneyPermission::PaymentsReport); }
    public function assessRisk(ActionInput $input,ActionContext $context): RiskAssessment { return new RiskAssessment(RiskLevel::Medium,['May confirm and allocate a high-confidence customer payment.'],null); }
    public function execute(ActionInput $input,ActionContext $context): ActionResult
    {
        if(!$input instanceof IngestPaymentEvidenceInput)throw new \InvalidArgumentException('IngestPaymentEvidenceInput is required.');
        $this->authorize($context);
        $key=IdempotencyKey::fromRaw($context->idempotencyKey);$claim=$this->idempotency->claim($context->companyId,self::ACTION_KEY,$key,RequestFingerprint::fromPayload($input->toArray()));
        if($claim->status===IdempotencyClaimStatus::Replay)return $this->result($claim->resultPayload??[],'money.payment.evidence_replayed');
        if($claim->status!==IdempotencyClaimStatus::Acquired)return new ActionResult(ResultStatus::Failed,'money.payment.idempotency_conflict',[],null,[['message'=>'Payment evidence action is already processing or conflicts with the existing key.']],null);
        try{
            $data=$this->transactions->run(function()use($input,$context):array{
                $duplicate=$this->evidence->findByHash($context->companyId,strtolower($input->rawPayloadHash));
                if($duplicate!==null){$id=$this->exceptions->create('duplicate_payment_evidence','high','payment_evidence',$duplicate->id,['raw_payload_hash'=>$duplicate->rawPayloadHash],$context);return ['duplicate'=>true,'evidence_id'=>$duplicate->id,'exception_id'=>$id];}
                $record=new PaymentEvidenceRecord($this->ids->uuid(),$context->companyId,$input->sourceType,Money::ofMinor($input->amountMinor,$input->currencyCode),$input->referenceText,$input->payerHint,strtolower($input->rawPayloadHash),$input->observedAt,$input->sourceDeviceId,'unique','unverified',$input->paymentMethod);
                $this->evidence->save($record,$context);$this->outbox->append(new OutboxEvent($this->ids->uuid(),$context->companyId,'money.payment.evidence_observed','payment_evidence',$record->id,['amount_minor'=>$input->amountMinor,'currency_code'=>strtoupper($input->currencyCode),'source_type'=>$input->sourceType],$this->clock->now(),$context));
                $ranked=$this->matcher->rank(new BankDepositEvidence($record->amount,$record->referenceText,$record->payerHint,$record->observedAt,$record->rawPayloadHash),$this->receivables->openCandidates($context->companyId,$record->amount->currency->value));
                $this->matches->saveRanked($context->companyId,$record->id,$ranked,$this->autoConfirmThreshold,$context);
                $top=$ranked[0]??null;if($top===null){$exception=$this->exceptions->create('unmatched_payment_evidence','medium','payment_evidence',$record->id,['amount_minor'=>$record->amount->minorAmount],$context);return ['duplicate'=>false,'evidence_id'=>$record->id,'match'=>null,'exception_id'=>$exception];}
                $match=['invoice_id'=>$top->invoiceId,'confidence_score'=>$top->confidenceScore,'status'=>$top->status->value,'reasons'=>$top->reasons];
                $this->outbox->append(new OutboxEvent($this->ids->uuid(),$context->companyId,'money.payment.match_proposed','payment_evidence',$record->id,['match'=>$match],$this->clock->now(),$context));
                if($top->confidenceScore>=$this->autoConfirmThreshold&&$top->policyMayAutoConfirm){
                    $this->access->assertAllowed($context,TitanMoneyPermission::PaymentsConfirm);$this->access->assertAllowed($context,TitanMoneyPermission::PaymentsAllocate);
                    $receivable=$this->receivables->get($context->companyId,$top->invoiceId)??throw new \RuntimeException('Matched receivable is no longer available.');
                    $confirmed=$this->confirmation->confirmAndAllocate($record,$receivable,$context);$this->evidence->markVerified($context->companyId,$record->id,$top->confidenceScore,'confirmed');return ['duplicate'=>false,'evidence_id'=>$record->id,'match'=>$match]+$confirmed;
                }
                $this->evidence->markVerified($context->companyId,$record->id,$top->confidenceScore,'review_required');return ['duplicate'=>false,'evidence_id'=>$record->id,'match'=>$match];
            });
            $code=($data['duplicate']??false)?'money.payment.duplicate_evidence':(isset($data['payment'])?'money.payment.confirmed_and_allocated':'money.payment.match_review_required');$this->idempotency->complete($context->companyId,self::ACTION_KEY,$key,$data);$this->audit->record(self::ACTION_KEY,$code,$context,$context->actor??throw new \RuntimeException('Audit actor required.'),$data);return $this->result($data,$code);
        }catch(\Throwable $e){$this->idempotency->fail($context->companyId,self::ACTION_KEY,$key,'payment_evidence_failed');if($context->actor!==null)$this->audit->record(self::ACTION_KEY,'failure',$context,$context->actor,['error'=>$e->getMessage()]);return new ActionResult(ResultStatus::Failed,'money.payment.evidence_failed',[],null,[['message'=>$e->getMessage()]],null);}
    }
    /** @param array<string,mixed> $data */ private function result(array $data,string $code): ActionResult{return new ActionResult(ResultStatus::Success,$code,$data,new GenerativeUiEnvelope('1.0',isset($data['payment'])?'money.payment-confirmed-card':'money.payment-match-card',$data,[SurfaceType::Workspace,SurfaceType::MobileCard,SurfaceType::TabletPanel,SurfaceType::Headless],[]),[],null);}
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Actions;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionResult;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ConfirmPaymentMatchInput;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\GenerativeUiEnvelope;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\RiskAssessment;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\ResultStatus;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\RiskLevel;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\SurfaceType;
use App\Domains\WorkCore\System\Modules\Finance\Application\Services\PaymentConfirmationService;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\FinanceAccessGate;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\TitanMoneyPermission;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AuditRecorder;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentEvidenceRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ReceivableRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TitanMoneyAction;
use RuntimeException;

final class ConfirmPaymentMatch implements TitanMoneyAction
{
    public const ACTION_KEY='money.confirm_payment_match';
    public function __construct(private readonly FinanceAccessGate $access,private readonly PaymentEvidenceRepository $evidence,private readonly ReceivableRepository $receivables,private readonly PaymentConfirmationService $confirmation,private readonly AuditRecorder $audit){}
    public function authorize(ActionContext $context): void{$this->access->assertAllowed($context,TitanMoneyPermission::PaymentsConfirm);$this->access->assertAllowed($context,TitanMoneyPermission::PaymentsAllocate);}
    public function assessRisk(ActionInput $input,ActionContext $context): RiskAssessment{return new RiskAssessment(RiskLevel::High,['Confirms a selected payment match and changes receivable and ledger state.'],null);}
    public function execute(ActionInput $input,ActionContext $context): ActionResult
    {
        if(!$input instanceof ConfirmPaymentMatchInput)throw new \InvalidArgumentException('ConfirmPaymentMatchInput is required.');$this->authorize($context);
        try{$record=$this->evidence->findById($context->companyId,$input->evidenceId)??throw new RuntimeException('Payment evidence not found.');$receivable=$this->receivables->get($context->companyId,$input->invoiceId)??throw new RuntimeException('Receivable not found.');$data=$this->confirmation->confirmAndAllocate($record,$receivable,$context);$this->evidence->markVerified($context->companyId,$record->id,100,'confirmed_by_user');$this->audit->record(self::ACTION_KEY,'success',$context,$context->actor??throw new RuntimeException('Audit actor required.'),$data);return new ActionResult(ResultStatus::Success,'money.payment.match_confirmed',$data,new GenerativeUiEnvelope('1.0','money.payment-confirmed-card',$data,[SurfaceType::Workspace,SurfaceType::MobileCard,SurfaceType::TabletPanel],[]),[],null);}catch(\Throwable $e){if($context->actor!==null)$this->audit->record(self::ACTION_KEY,'failure',$context,$context->actor,['error'=>$e->getMessage()]);return new ActionResult(ResultStatus::Failed,'money.payment.match_confirmation_failed',[],null,[['message'=>$e->getMessage()]],null);}
    }
}

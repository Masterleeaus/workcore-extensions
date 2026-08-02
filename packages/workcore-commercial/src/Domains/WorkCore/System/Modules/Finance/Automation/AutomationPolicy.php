<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation;

use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\RiskLevel;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DomainException;

final readonly class AutomationPolicy
{
    public function __construct(public string $companyId, public AutonomyLevel $level, public Money $automaticAmountLimit, public int $automaticMatchThreshold=95) {}

    public static function defaultFor(string $companyId, AutonomyLevel $level, Money $automaticAmountLimit): self { return new self($companyId,$level,$automaticAmountLimit); }

    public function decide(FinancialActionType $action, RiskLevel $risk, ?Money $amount=null, ?int $confidenceScore=null): AutomationDecision
    {
        if ($amount && ! $amount->currency->equals($this->automaticAmountLimit->currency)) throw new DomainException('Automation policy amount currency mismatch.');
        if (in_array($action,[FinancialActionType::Refund,FinancialActionType::WriteOff,FinancialActionType::ChangePaymentCredentials,FinancialActionType::ClosePeriod,FinancialActionType::ApproveBudget,FinancialActionType::ApprovePurchaseOrder,FinancialActionType::ApproveWorkforceLoan,FinancialActionType::DisburseWorkforceLoan,FinancialActionType::VoidPosSale],true) || $risk===RiskLevel::Critical) return AutomationDecision::RequestApproval;
        if ($amount && $amount->compareTo($this->automaticAmountLimit)>0) return AutomationDecision::RequestApproval;
        if ($action===FinancialActionType::ConfirmPayment && ($confidenceScore ?? 0)<$this->automaticMatchThreshold) return AutomationDecision::RequestApproval;
        return match($this->level) {
            AutonomyLevel::Assist => AutomationDecision::Propose,
            AutonomyLevel::Approve => AutomationDecision::RequestApproval,
            AutonomyLevel::Auto => in_array($risk,[RiskLevel::Low,RiskLevel::Medium],true) ? AutomationDecision::Execute : AutomationDecision::RequestApproval,
            AutonomyLevel::Managed => $risk===RiskLevel::Critical ? AutomationDecision::RequestApproval : AutomationDecision::Execute,
        };
    }
}

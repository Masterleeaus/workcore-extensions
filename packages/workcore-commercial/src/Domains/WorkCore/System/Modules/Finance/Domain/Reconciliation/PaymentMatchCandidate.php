<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation;

final readonly class PaymentMatchCandidate
{
    /** @param list<string> $reasons */
    public function __construct(public string $invoiceId, public int $confidenceScore, public MatchStatus $status, public array $reasons, public bool $policyMayAutoConfirm) {}

    public function toArray(): array { return ['invoice_id'=>$this->invoiceId,'confidence_score'=>$this->confidenceScore,'status'=>$this->status->value,'reasons'=>$this->reasons,'policy_may_auto_confirm'=>$this->policyMayAutoConfirm]; }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ReceivableRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Receivables\ReceivableAccount;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation\OpenReceivableCandidate;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use RuntimeException;

final class InMemoryReceivableRepository implements ReceivableRepository
{
    /** @var array<string,ReceivableAccount> */ public array $accounts=[];
    public function put(ReceivableAccount $account): void { $this->accounts[$account->invoiceId]=$account; }
    public function openCandidates(string $companyId,string $currencyCode): array { $result=[]; foreach($this->accounts as $a) if($a->companyId===$companyId&&$a->balance->currency->value===strtoupper($currencyCode)&&$a->balance->minorAmount>0&&!in_array($a->state,['voided','credited','paid'],true)) $result[]=new OpenReceivableCandidate($a->invoiceId,$a->balance,$a->paymentReference,$a->payerHint,$a->issuedAt); return $result; }
    public function get(string $companyId,string $invoiceId): ?ReceivableAccount { $a=$this->accounts[$invoiceId]??null; return $a?->companyId===$companyId?$a:null; }
    public function allocate(string $companyId,string $invoiceId,int $amountMinor,ActionContext $context): ReceivableAccount { $a=$this->get($companyId,$invoiceId)??throw new RuntimeException('Receivable not found.'); if($amountMinor<=0||$amountMinor>$a->balance->minorAmount)throw new RuntimeException('Allocation exceeds receivable balance.'); $a->balance=Money::ofMinor($a->balance->minorAmount-$amountMinor,$a->balance->currency->value); $a->state=$a->balance->minorAmount===0?'paid':'partially_paid'; return $a; }
}

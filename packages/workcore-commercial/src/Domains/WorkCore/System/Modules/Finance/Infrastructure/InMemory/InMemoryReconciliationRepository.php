<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ReconciliationRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation\ReconciliationSession;
use DateTimeImmutable;

final class InMemoryReconciliationRepository implements ReconciliationRepository
{
    /** @var array<string,ReconciliationSession> */ private array $sessions=[];
    public function save(ReconciliationSession $session):void{$this->sessions[$session->companyId.'|'.$session->id]=$session;}
    public function get(string $companyId,string $reconciliationId):?ReconciliationSession{return $this->sessions[$companyId.'|'.$reconciliationId]??null;}
    public function close(string $companyId,string $reconciliationId,ActionContext $context,DateTimeImmutable $closedAt):void{$session=$this->get($companyId,$reconciliationId)??throw new \RuntimeException('Reconciliation not found.');$this->save(new ReconciliationSession($session->id,$session->companyId,$session->bankAccountId,$session->statementClosingBalanceMinor,$session->reconciledClosingBalanceMinor,$session->currencyCode,$session->unresolvedLineCount,'closed'));}
}

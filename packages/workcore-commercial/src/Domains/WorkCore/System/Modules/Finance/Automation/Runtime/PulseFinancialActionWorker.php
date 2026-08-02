<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Automation\Runtime;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\InterfaceOrigin;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\ResultStatus;
use App\Domains\WorkCore\System\Modules\Finance\Audit\ActorType;
use App\Domains\WorkCore\System\Modules\Finance\Audit\AuditActor;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinancialActionInvoker;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ScheduledActionQueue;
use DateTimeImmutable;

final class PulseFinancialActionWorker
{
    public function __construct(private readonly ScheduledActionQueue $queue,private readonly FinancialActionInvoker $invoker,private readonly string $workerId='titan-money-pulse',private readonly int $maxAttempts=5){}
    /** @return array{claimed:int,completed:int,retried:int,failed:int} */
    public function run(DateTimeImmutable $now,int $limit=50):array
    {
        $records=$this->queue->claimDue($now,$limit,$this->workerId,300);$report=['claimed'=>count($records),'completed'=>0,'retried'=>0,'failed'=>0];
        foreach($records as $record){try{$context=new ActionContext($record->companyId,null,null,InterfaceOrigin::Pulse,null,null,null,$record->workflowId,null,$record->correlationId,'scheduled:'.$record->id,new AuditActor(ActorType::Workflow,$this->workerId,'Titan Pulse finance worker'));$result=$this->invoker->invoke($record->actionKey,$record->payload,$context);if(in_array($result->status,[ResultStatus::Success,ResultStatus::PendingApproval],true)){$this->queue->complete($record->id,$result->toArray(),$now);$report['completed']++;continue;}if(in_array($result->status,[ResultStatus::ValidationFailed,ResultStatus::Rejected],true)){$this->queue->fail($record->id,$result->code,$this->message($result->messages),$now);$report['failed']++;continue;}$this->handleFailure($record,$result->code,$this->message($result->messages),$now,$report);}catch(\Throwable $e){$this->handleFailure($record,'money.worker.exception',$e->getMessage(),$now,$report);}}
        return $report;
    }
    /** @param array{claimed:int,completed:int,retried:int,failed:int} $report */
    private function handleFailure(ScheduledActionRecord $record,string $code,string $message,DateTimeImmutable $now,array &$report):void{$attempt=$record->attemptCount+1;if($attempt>=$this->maxAttempts){$this->queue->fail($record->id,$code,$message,$now);$report['failed']++;return;}$minutes=[5,15,60,240][$attempt-1]??720;$this->queue->retry($record->id,$code,$message,$now->modify("+$minutes minutes"));$report['retried']++;}
    /** @param list<array<string,mixed>|string> $messages */ private function message(array $messages):string{$first=$messages[0]??'Action failed.';return is_array($first)?(string)($first['message']??'Action failed.'):(string)$first;}
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Integrations\Runtime;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\OutboxRelayQueue;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\SignalPublisher;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TitanTalkTransport;
use DateTimeImmutable;

final class TitanMoneyOutboxRelay
{
    public function __construct(private readonly OutboxRelayQueue $queue,private readonly SignalPublisher $signals,private readonly TitanTalkTransport $talk,private readonly string $workerId='titan-money-outbox',private readonly int $maxAttempts=8){}
    /** @return array{claimed:int,published:int,retried:int,dead_lettered:int} */
    public function run(DateTimeImmutable $now,int $limit=100):array{$records=$this->queue->claimPending($now,$limit,$this->workerId,300);$report=['claimed'=>count($records),'published'=>0,'retried'=>0,'dead_lettered'=>0];foreach($records as $record){try{$externalId=null;if(str_starts_with($record->eventType,'talk.'))$externalId=$this->talk->send($record->eventType,$record->payload,$record->companyId,$record->correlationId);else $this->signals->publish($record->eventType,['company_id'=>$record->companyId,'aggregate_type'=>$record->aggregateType,'aggregate_id'=>$record->aggregateId,'correlation_id'=>$record->correlationId,'occurred_at'=>$record->occurredAt->format(DATE_ATOM)]+$record->payload);$this->queue->published($record->id,$now,$externalId);$report['published']++;}catch(\Throwable $e){$attempt=$record->publishAttempts+1;if($attempt>=$this->maxAttempts){$this->queue->deadLetter($record->id,'outbox_publish_failed',$e->getMessage(),$now);$report['dead_lettered']++;}else{$minutes=min(720,2**min($attempt,8));$this->queue->retry($record->id,'outbox_publish_failed',$e->getMessage(),$now->modify("+$minutes minutes"));$report['retried']++;}}}return $report;}
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\OutboxRelayQueue;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Events\OutboxRelayRecord;
use DateTimeImmutable;

final class InMemoryOutboxRelayQueue implements OutboxRelayQueue
{
    /** @var array<string,OutboxRelayRecord> */ public array $records=[];
    /** @var array<string,string> */ public array $states=[];
    /** @var array<string,DateTimeImmutable> */ public array $nextAttemptAt=[];
    /** @var array<string,DateTimeImmutable> */ public array $leasedUntil=[];

    public function seed(OutboxRelayRecord $record):void
    {
        $this->records[$record->id]=$record;
        $this->states[$record->id]='pending';
        $this->nextAttemptAt[$record->id]=$record->occurredAt;
    }

    public function claimPending(DateTimeImmutable $now,int $limit,string $workerId,int $leaseSeconds):array
    {
        $out=[];
        foreach($this->records as $id=>$record){
            if(count($out)>=$limit)break;
            $state=$this->states[$id]??'';
            $recoverable=$state==='publishing'&&isset($this->leasedUntil[$id])&&$this->leasedUntil[$id]<$now;
            if(($state==='pending'||$recoverable)&&$this->nextAttemptAt[$id]<=$now){
                $this->states[$id]='publishing';
                $this->leasedUntil[$id]=$now->modify("+$leaseSeconds seconds");
                $out[]=$record;
            }
        }
        return $out;
    }

    public function published(string $id,DateTimeImmutable $publishedAt,?string $externalId=null):void{$this->states[$id]='published';unset($this->leasedUntil[$id]);}
    public function retry(string $id,string $errorCode,string $errorMessage,DateTimeImmutable $nextAttemptAt):void{$this->states[$id]='pending';$this->nextAttemptAt[$id]=$nextAttemptAt;unset($this->leasedUntil[$id]);}
    public function deadLetter(string $id,string $errorCode,string $errorMessage,DateTimeImmutable $failedAt):void{$this->states[$id]='dead_letter';unset($this->leasedUntil[$id]);}
}

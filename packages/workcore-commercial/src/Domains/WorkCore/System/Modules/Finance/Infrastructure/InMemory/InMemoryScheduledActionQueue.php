<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Automation\Runtime\ScheduledActionRecord;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ScheduledActionQueue;
use DateTimeImmutable;

final class InMemoryScheduledActionQueue implements ScheduledActionQueue
{
    /** @var array<string,ScheduledActionRecord> */ public array $records=[];
    /** @var array<string,string> */ public array $states=[];
    /** @var array<string,DateTimeImmutable> */ public array $nextRunAt=[];
    /** @var array<string,DateTimeImmutable> */ public array $leasedUntil=[];

    public function seed(ScheduledActionRecord $record):void
    {
        $this->records[$record->id]=$record;
        $this->states[$record->id]='scheduled';
        $this->nextRunAt[$record->id]=$record->runAt;
    }

    public function claimDue(DateTimeImmutable $now,int $limit,string $workerId,int $leaseSeconds):array
    {
        $out=[];
        foreach($this->records as $id=>$record){
            if(count($out)>=$limit)break;
            $state=$this->states[$id]??'';
            $recoverable=$state==='processing'&&isset($this->leasedUntil[$id])&&$this->leasedUntil[$id]<$now;
            if(($state==='scheduled'||$recoverable)&&$this->nextRunAt[$id]<=$now){
                $this->states[$id]='processing';
                $this->leasedUntil[$id]=$now->modify("+$leaseSeconds seconds");
                $out[]=$record;
            }
        }
        return $out;
    }

    public function complete(string $id,array $result,DateTimeImmutable $finishedAt):void{$this->states[$id]='completed';unset($this->leasedUntil[$id]);}
    public function retry(string $id,string $errorCode,string $errorMessage,DateTimeImmutable $nextRunAt):void{$this->states[$id]='scheduled';$this->nextRunAt[$id]=$nextRunAt;unset($this->leasedUntil[$id]);}
    public function fail(string $id,string $errorCode,string $errorMessage,DateTimeImmutable $finishedAt):void{$this->states[$id]='failed';unset($this->leasedUntil[$id]);}
}

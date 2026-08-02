<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\ExternalEventInboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Integrations\Runtime\ExternalEventReceipt;
use App\Domains\WorkCore\System\Modules\Finance\Integrations\Runtime\ExternalEventReceiptStatus;
use DateTimeImmutable;

final class InMemoryExternalEventInboxRepository implements ExternalEventInboxRepository
{
    /** @var array<string,array<string,mixed>> */ public array $events=[];

    public function claim(string $companyId,string $sourceSystem,string $externalEventId,string $eventType,string $payloadHash,DateTimeImmutable $receivedAt):ExternalEventReceipt
    {
        $key="$companyId|$sourceSystem|$externalEventId";
        if(isset($this->events[$key])) {
            $event=$this->events[$key];
            if($event['payload_hash']!==$payloadHash)return new ExternalEventReceipt(ExternalEventReceiptStatus::Conflict,$key);
            $stale=($event['state']??'')==='processing'&&isset($event['lease_until'])&&$event['lease_until']<$receivedAt;
            if(($event['state']??'')==='failed'||$stale){
                $this->events[$key]['state']='processing';
                $this->events[$key]['received_at']=$receivedAt;
                $this->events[$key]['lease_until']=$receivedAt->modify('+5 minutes');
                $this->events[$key]['attempt_count']=(int)($event['attempt_count']??0)+1;
                unset($this->events[$key]['failure_code']);
                return new ExternalEventReceipt(ExternalEventReceiptStatus::Acquired,$key);
            }
            return new ExternalEventReceipt(ExternalEventReceiptStatus::Replay,$key);
        }
        $this->events[$key]=[
            'payload_hash'=>$payloadHash,
            'event_type'=>$eventType,
            'state'=>'processing',
            'received_at'=>$receivedAt,
            'lease_until'=>$receivedAt->modify('+5 minutes'),
            'attempt_count'=>1,
        ];
        return new ExternalEventReceipt(ExternalEventReceiptStatus::Acquired,$key);
    }

    public function complete(string $companyId,string $sourceSystem,string $externalEventId,DateTimeImmutable $processedAt):void
    {
        $key="$companyId|$sourceSystem|$externalEventId";
        $this->events[$key]['state']='processed';
        $this->events[$key]['processed_at']=$processedAt;
        $this->events[$key]['lease_until']=null;
    }

    public function fail(string $companyId,string $sourceSystem,string $externalEventId,string $failureCode):void
    {
        $key="$companyId|$sourceSystem|$externalEventId";
        $this->events[$key]['state']='failed';
        $this->events[$key]['failure_code']=$failureCode;
        $this->events[$key]['lease_until']=null;
    }
}

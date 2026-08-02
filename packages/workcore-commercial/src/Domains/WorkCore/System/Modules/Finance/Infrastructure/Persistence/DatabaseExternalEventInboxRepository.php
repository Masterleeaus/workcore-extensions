<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\ExternalEventInboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Integrations\Runtime\ExternalEventReceipt;
use App\Domains\WorkCore\System\Modules\Finance\Integrations\Runtime\ExternalEventReceiptStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class DatabaseExternalEventInboxRepository implements ExternalEventInboxRepository
{
    public function __construct(private readonly IdentifierGenerator $ids) {}

    public function claim(string $companyId,string $sourceSystem,string $externalEventId,string $eventType,string $payloadHash,DateTimeImmutable $receivedAt):ExternalEventReceipt
    {
        return DB::transaction(function()use($companyId,$sourceSystem,$externalEventId,$eventType,$payloadHash,$receivedAt):ExternalEventReceipt{
            $existing=DB::table('tm_external_event_inbox')
                ->where(['company_id'=>$companyId,'source_system'=>$sourceSystem,'external_event_id'=>$externalEventId])
                ->lockForUpdate()
                ->first();
            $leaseUntil=$receivedAt->modify('+5 minutes');
            if($existing!==null){
                if(!hash_equals((string)$existing->payload_hash,$payloadHash))return new ExternalEventReceipt(ExternalEventReceiptStatus::Conflict,(string)$existing->id);
                $stale=(string)$existing->state==='processing'&&$existing->processing_lease_until!==null&&new DateTimeImmutable((string)$existing->processing_lease_until)<$receivedAt;
                if((string)$existing->state==='failed'||$stale){
                    DB::table('tm_external_event_inbox')->where('id',$existing->id)->update([
                        'state'=>'processing','received_at'=>$receivedAt,'processed_at'=>null,'failure_code'=>null,
                        'processing_lease_until'=>$leaseUntil,'attempt_count'=>((int)$existing->attempt_count)+1,'updated_at'=>now(),
                    ]);
                    return new ExternalEventReceipt(ExternalEventReceiptStatus::Acquired,(string)$existing->id);
                }
                return new ExternalEventReceipt(ExternalEventReceiptStatus::Replay,(string)$existing->id);
            }
            $id=$this->ids->uuid();
            DB::table('tm_external_event_inbox')->insert([
                'id'=>$id,'company_id'=>$companyId,'source_system'=>$sourceSystem,'external_event_id'=>$externalEventId,'event_type'=>$eventType,
                'payload_hash'=>$payloadHash,'state'=>'processing','received_at'=>$receivedAt,'processing_lease_until'=>$leaseUntil,
                'attempt_count'=>1,'created_at'=>now(),'updated_at'=>now(),
            ]);
            return new ExternalEventReceipt(ExternalEventReceiptStatus::Acquired,$id);
        });
    }

    public function complete(string $companyId,string $sourceSystem,string $externalEventId,DateTimeImmutable $processedAt):void
    {
        DB::table('tm_external_event_inbox')->where('company_id',$companyId)->where('source_system',$sourceSystem)->where('external_event_id',$externalEventId)->update([
            'state'=>'processed','processed_at'=>$processedAt,'processing_lease_until'=>null,'updated_at'=>now(),
        ]);
    }

    public function fail(string $companyId,string $sourceSystem,string $externalEventId,string $failureCode):void
    {
        DB::table('tm_external_event_inbox')->where('company_id',$companyId)->where('source_system',$sourceSystem)->where('external_event_id',$externalEventId)->update([
            'state'=>'failed','failure_code'=>$failureCode,'processing_lease_until'=>null,'updated_at'=>now(),
        ]);
    }
}

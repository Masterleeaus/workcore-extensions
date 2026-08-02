<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AutomationScheduler;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class DatabaseAutomationScheduler implements AutomationScheduler
{
    public function __construct(private readonly IdentifierGenerator $ids) {}
    public function schedule(string $actionKey,array $payload,DateTimeImmutable $runAt,ActionContext $context): string { $hash=hash('sha256',$context->companyId.'|'.$actionKey.'|'.json_encode($payload,JSON_THROW_ON_ERROR).'|'.$runAt->format(DATE_ATOM));
        $existing=DB::table('tm_scheduled_actions')->where('company_id',$context->companyId)->where('action_key',$actionKey)->where('idempotency_key_hash',$hash)->first(); if($existing!==null)return (string)$existing->id;
        $id=$this->ids->uuid(); DB::table('tm_scheduled_actions')->insert(['id'=>$id,'company_id'=>$context->companyId,'action_key'=>$actionKey,'entity_type'=>isset($payload['invoice_id'])?'invoice':null,'entity_id'=>$payload['invoice_id']??null,'payload'=>json_encode($payload,JSON_THROW_ON_ERROR),'run_at'=>$runAt,
            'state'=>'scheduled','attempt_count'=>0,'idempotency_key_hash'=>$hash,'workflow_id'=>$context->workflowId,'correlation_id'=>$context->correlationId,'created_at'=>now(),'updated_at'=>now()]); return $id; }
    public function cancel(string $scheduledActionId,ActionContext $context): void { DB::table('tm_scheduled_actions')->where('company_id',$context->companyId)->where('id',$scheduledActionId)->where('state','scheduled')->update(['state'=>'cancelled','cancelled_at'=>now(),'updated_at'=>now()]); }
    public function cancelForEntity(string $companyId,string $entityType,string $entityId,ActionContext $context): int { return DB::table('tm_scheduled_actions')->where('company_id',$companyId)->where('entity_type',$entityType)->where('entity_id',$entityId)->where('state','scheduled')->update(['state'=>'cancelled','cancelled_at'=>now(),'updated_at'=>now()]); }
}

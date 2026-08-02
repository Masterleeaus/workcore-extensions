<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Audit\AuditActor;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AuditRecorder;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use Illuminate\Support\Facades\DB;

final class DatabaseAuditRecorder implements AuditRecorder
{
    public function __construct(private readonly IdentifierGenerator $ids) {}
    public function record(string $actionKey,string $outcome,ActionContext $context,AuditActor $actor,array $metadata=[]): void { DB::table('tm_audit_events')->insert(['id'=>$this->ids->uuid(),'company_id'=>$context->companyId,'action_key'=>$actionKey,'outcome'=>$outcome,'actor_type'=>$actor->type->value,'actor_id'=>$actor->id,'origin'=>$context->origin->value,
        'correlation_id'=>$context->correlationId,'conversation_id'=>$context->conversationId,'workflow_id'=>$context->workflowId,'approval_id'=>$context->approvalId,'metadata'=>$metadata?json_encode($metadata,JSON_THROW_ON_ERROR):null,'occurred_at'=>now(),'created_at'=>now(),'updated_at'=>now()]); }
}

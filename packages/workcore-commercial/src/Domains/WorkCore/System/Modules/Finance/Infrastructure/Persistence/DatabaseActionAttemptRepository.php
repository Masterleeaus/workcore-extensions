<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionAttemptRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabaseActionAttemptRepository implements ActionAttemptRepository
{
    public function __construct(private readonly IdentifierGenerator $ids,private readonly Clock $clock){}
    public function record(string $actionKey,string $state,int $attemptNumber,array $result,ActionContext $context,?string $errorCode=null): void {$actor=$context->actor??throw new RuntimeException('Audit actor required.');DB::table('tm_action_attempts')->insert(['id'=>$this->ids->uuid(),'company_id'=>$context->companyId,'scheduled_action_id'=>null,'action_key'=>$actionKey,'state'=>$state,'attempt_number'=>$attemptNumber,'actor_type'=>$actor->type->value,'actor_id'=>$actor->id,'origin'=>$context->origin->value,'correlation_id'=>$context->correlationId,'error_code'=>$errorCode,'error_message'=>$result['error']??null,'result_payload'=>$result?json_encode($result,JSON_THROW_ON_ERROR):null,'started_at'=>$this->clock->now(),'finished_at'=>$this->clock->now(),'created_at'=>now(),'updated_at'=>now()]);}
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\CollectionMessageGateway;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabaseCollectionMessageGateway implements CollectionMessageGateway
{
    public function __construct(private readonly IdentifierGenerator $ids,private readonly Clock $clock){}
    public function queue(string $invoiceId,string $stepCode,array $channels,string $tone,int $balanceMinor,string $currencyCode,ActionContext $context): array
    {
        $actor=$context->actor??throw new RuntimeException('Audit actor required.');$result=[];
        foreach($channels as $channel){$existing=DB::table('tm_collection_messages')->where('company_id',$context->companyId)->where('invoice_id',$invoiceId)->where('step_code',$stepCode)->where('channel',$channel)->where('correlation_id',$context->correlationId)->first();if($existing!==null){$result[]=(string)$existing->id;continue;}$id=$this->ids->uuid();DB::table('tm_collection_messages')->insert(['id'=>$id,'company_id'=>$context->companyId,'invoice_id'=>$invoiceId,'step_code'=>$stepCode,'channel'=>$channel,'tone'=>$tone,'state'=>'queued','balance_minor'=>$balanceMinor,'currency_code'=>strtoupper($currencyCode),'actor_type'=>$actor->type->value,'actor_id'=>$actor->id,'origin'=>$context->origin->value,'correlation_id'=>$context->correlationId,'queued_at'=>$this->clock->now(),'created_at'=>now(),'updated_at'=>now()]);$result[]=$id;}
        return $result;
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinancialExceptionRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use Illuminate\Support\Facades\DB;

final class DatabaseFinancialExceptionRepository implements FinancialExceptionRepository
{
    public function __construct(private readonly IdentifierGenerator $ids){}
    public function create(string $type,string $severity,?string $entityType,?string $entityId,array $evidence,ActionContext $context): string {$id=$this->ids->uuid();DB::table('tm_financial_exceptions')->insert(['id'=>$id,'company_id'=>$context->companyId,'exception_type'=>$type,'severity'=>$severity,'entity_type'=>$entityType,'entity_id'=>$entityId,'state'=>'open','evidence'=>json_encode($evidence,JSON_THROW_ON_ERROR),'assigned_to_user_id'=>null,'resolved_at'=>null,'resolved_by_actor_type'=>null,'resolved_by_actor_id'=>null,'created_at'=>now(),'updated_at'=>now()]);return $id;}
}

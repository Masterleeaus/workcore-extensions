<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinancialExceptionRepository;

final class InMemoryFinancialExceptionRepository implements FinancialExceptionRepository
{
    /** @var list<array<string,mixed>> */ public array $exceptions=[];
    public function create(string $type,string $severity,?string $entityType,?string $entityId,array $evidence,ActionContext $context): string { $id='exception-'.(count($this->exceptions)+1); $this->exceptions[]=['id'=>$id,'type'=>$type,'severity'=>$severity,'entity_type'=>$entityType,'entity_id'=>$entityId,'evidence'=>$evidence,'context'=>$context]; return $id; }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\CollectionMessageGateway;

final class InMemoryCollectionMessageGateway implements CollectionMessageGateway
{
    /** @var list<array<string,mixed>> */ public array $messages=[];
    public function queue(string $invoiceId,string $stepCode,array $channels,string $tone,int $balanceMinor,string $currencyCode,ActionContext $context): array
    {
        $ids=[];
        foreach($channels as $channel){ $id='collection-message-'.(count($this->messages)+1); $this->messages[]=['id'=>$id,'invoice_id'=>$invoiceId,'step_code'=>$stepCode,'channel'=>$channel,'tone'=>$tone,'balance_minor'=>$balanceMinor,'currency_code'=>$currencyCode,'context'=>$context]; $ids[]=$id; }
        return $ids;
    }
}

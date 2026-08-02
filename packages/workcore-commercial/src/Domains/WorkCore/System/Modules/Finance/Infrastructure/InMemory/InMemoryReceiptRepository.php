<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ReceiptRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\ReceiptRecord;

final class InMemoryReceiptRepository implements ReceiptRepository
{
    /** @var array<string,ReceiptRecord> */ public array $receipts=[];
    public function create(ReceiptRecord $receipt,ActionContext $context): ReceiptRecord { return $this->receipts[$receipt->id]=$receipt; }
}

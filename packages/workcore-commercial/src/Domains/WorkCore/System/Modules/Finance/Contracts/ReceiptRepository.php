<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\ReceiptRecord;

interface ReceiptRepository
{
    public function create(ReceiptRecord $receipt, ActionContext $context): ReceiptRecord;
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentRequestRecord;

interface PaymentRequestRepository
{
    public function findOpenForInvoice(string $companyId, string $invoiceId): ?PaymentRequestRecord;

    public function create(PaymentRequestRecord $request, ActionContext $context): PaymentRequestRecord;
}

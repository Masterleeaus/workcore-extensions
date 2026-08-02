<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;

interface EInvoiceAdapter
{
    public function submit(array $issuedInvoiceSnapshot, ActionContext $context): array;
    public function status(string $externalIdentifier, ActionContext $context): array;
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;

interface CollectionMessageGateway
{
    /** @param list<string> $channels @return list<string> */
    public function queue(string $invoiceId, string $stepCode, array $channels, string $tone, int $balanceMinor, string $currencyCode, ActionContext $context): array;
}

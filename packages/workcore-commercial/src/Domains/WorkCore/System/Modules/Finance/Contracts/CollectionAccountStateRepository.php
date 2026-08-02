<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Automation\Collections\CollectionAccountState;

interface CollectionAccountStateRepository
{
    public function get(string $companyId, string $invoiceId): ?CollectionAccountState;
}

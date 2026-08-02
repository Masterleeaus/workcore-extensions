<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Integrations\Banking\BankImportRow;

interface BankTransactionWriter
{
    public function storeIfNew(string $companyId, string $bankAccountId, string $importId, BankImportRow $row): bool;
}

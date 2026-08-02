<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\BankTransactionWriter;
use App\Domains\WorkCore\System\Modules\Finance\Integrations\Banking\BankImportRow;

final class InMemoryBankTransactionWriter implements BankTransactionWriter
{
    /** @var array<string,array<string,mixed>> */ public array $rows = [];
    public function storeIfNew(string $companyId, string $bankAccountId, string $importId, BankImportRow $row): bool
    {
        $key = $companyId . '|' . $row->transactionHash;
        if (isset($this->rows[$key])) return false;
        $this->rows[$key] = compact('companyId','bankAccountId','importId','row');
        return true;
    }
}

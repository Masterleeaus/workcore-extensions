<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\DTOs;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;
use InvalidArgumentException;

final readonly class ImportBankStatementInput implements ActionInput
{
    public function __construct(
        public string $bankAccountId,
        public string $currencyCode,
        public string $csv,
        public string $filename,
    ) {
        if (trim($bankAccountId) === '' || trim($csv) === '' || ! preg_match('/^[A-Z]{3}$/', strtoupper($currencyCode))) {
            throw new InvalidArgumentException('Bank account, currency and CSV content are required.');
        }
        if (strlen($csv) > 5_000_000) {
            throw new InvalidArgumentException('Bank statement CSV exceeds the 5 MB input limit.');
        }
    }

    public function toArray(): array
    {
        return [
            'bank_account_id' => $this->bankAccountId,
            'currency_code' => strtoupper($this->currencyCode),
            'filename' => $this->filename,
            'file_hash' => hash('sha256', $this->csv),
        ];
    }
}

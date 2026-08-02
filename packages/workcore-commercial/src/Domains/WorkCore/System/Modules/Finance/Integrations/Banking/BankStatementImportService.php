<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Integrations\Banking;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\BankImportRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\BankTransactionWriter;
use DateTimeImmutable;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use InvalidArgumentException;

final class BankStatementImportService
{
    public function __construct(private readonly AustralianBankCsvParser $parser, private readonly BankTransactionWriter $writer, private readonly IdentifierGenerator $ids, private readonly ?BankImportRepository $imports = null) {}

    public function import(string $companyId, string $bankAccountId, string $csv, string $currencyCode, ?ActionContext $context = null, ?DateTimeImmutable $occurredAt = null, ?string $fileHash = null): BankStatementImportResult
    {
        if (trim($companyId) === '' || trim($bankAccountId) === '') throw new InvalidArgumentException('Company and bank account are required.');
        $importId = $this->ids->uuid();
        $parsed = $this->parser->parse($csv, $currencyCode);
        $at = $occurredAt ?? new DateTimeImmutable();
        if ($this->imports !== null) {
            if ($context === null) throw new InvalidArgumentException('Action context is required when bank-import batch persistence is enabled.');
            $this->imports->start($importId, $companyId, $bankAccountId, $fileHash ?? hash('sha256', $csv), count($parsed->rows) + count($parsed->errors), $context, $at);
        }
        $imported = 0; $duplicates = 0;
        foreach ($parsed->rows as $row) $this->writer->storeIfNew($companyId, $bankAccountId, $importId, $row) ? $imported++ : $duplicates++;
        if ($this->imports !== null) $this->imports->complete($importId, $imported, $duplicates, $parsed->errors, $at);
        return new BankStatementImportResult($importId, $imported, $duplicates, $parsed->errors);
    }
}

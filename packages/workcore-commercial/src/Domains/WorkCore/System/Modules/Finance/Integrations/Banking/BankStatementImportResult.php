<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Integrations\Banking;

final readonly class BankStatementImportResult
{
    /** @param list<array{row:int,message:string}> $errors */
    public function __construct(public string $importId, public int $importedCount, public int $duplicateCount, public array $errors) {}
}

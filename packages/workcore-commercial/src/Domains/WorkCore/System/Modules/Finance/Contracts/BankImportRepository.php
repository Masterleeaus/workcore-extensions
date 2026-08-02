<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use DateTimeImmutable;

interface BankImportRepository
{
    public function start(
        string $importId,
        string $companyId,
        string $bankAccountId,
        string $fileHash,
        int $rowCount,
        ActionContext $context,
        DateTimeImmutable $startedAt,
    ): void;

    /** @param list<array{row:int,message:string}> $errors */
    public function complete(
        string $importId,
        int $importedCount,
        int $duplicateCount,
        array $errors,
        DateTimeImmutable $completedAt,
    ): void;
}

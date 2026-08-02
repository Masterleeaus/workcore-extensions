<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Integrations\Banking;

final readonly class BankImportParseResult
{
    /** @param list<BankImportRow> $rows @param list<array{row:int,message:string}> $errors */
    public function __construct(public array $rows, public array $errors) {}
}

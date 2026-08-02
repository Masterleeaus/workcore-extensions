<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\BankImportRepository;
use DateTimeImmutable;
use RuntimeException;

final class InMemoryBankImportRepository implements BankImportRepository
{
    /** @var list<array<string,mixed>> */
    public array $imports = [];

    public function start(string $importId,string $companyId,string $bankAccountId,string $fileHash,int $rowCount,ActionContext $context,DateTimeImmutable $startedAt):void
    {
        $actor=$context->actor??throw new RuntimeException('Audit actor required.');
        $this->imports[]=['id'=>$importId,'company_id'=>$companyId,'bank_account_id'=>$bankAccountId,'file_hash'=>$fileHash,'row_count'=>$rowCount,'state'=>'processing','actor'=>$actor->toArray(),'started_at'=>$startedAt];
    }

    public function complete(string $importId,int $importedCount,int $duplicateCount,array $errors,DateTimeImmutable $completedAt):void
    {
        foreach($this->imports as &$import){if($import['id']!==$importId)continue;$import['state']='completed';$import['imported_count']=$importedCount;$import['duplicate_count']=$duplicateCount;$import['error_count']=count($errors);$import['errors']=$errors;$import['completed_at']=$completedAt;return;}
        throw new RuntimeException('Bank import batch was not started.');
    }
}

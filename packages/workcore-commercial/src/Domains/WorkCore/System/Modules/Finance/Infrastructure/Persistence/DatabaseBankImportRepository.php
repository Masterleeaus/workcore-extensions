<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\BankImportRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabaseBankImportRepository implements BankImportRepository
{
    public function start(string $importId,string $companyId,string $bankAccountId,string $fileHash,int $rowCount,ActionContext $context,DateTimeImmutable $startedAt):void
    {
        $actor=$context->actor??throw new RuntimeException('Audit actor required.');
        DB::table('tm_bank_imports')->insert([
            'id'=>$importId,'company_id'=>$companyId,'bank_account_id'=>$bankAccountId,'source_type'=>'csv','file_hash'=>$fileHash,'state'=>'processing','row_count'=>$rowCount,
            'imported_count'=>0,'duplicate_count'=>0,'error_count'=>0,'errors'=>null,'actor_type'=>$actor->type->value,'actor_id'=>$actor->id,'origin'=>$context->origin->value,
            'correlation_id'=>$context->correlationId,'completed_at'=>null,'created_at'=>$startedAt,'updated_at'=>$startedAt,
        ]);
    }

    public function complete(string $importId,int $importedCount,int $duplicateCount,array $errors,DateTimeImmutable $completedAt):void
    {
        DB::table('tm_bank_imports')->where('id',$importId)->update([
            'state'=>'completed','imported_count'=>$importedCount,'duplicate_count'=>$duplicateCount,'error_count'=>count($errors),
            'errors'=>$errors===[]?null:json_encode($errors,JSON_THROW_ON_ERROR),'completed_at'=>$completedAt,'updated_at'=>$completedAt,
        ]);
    }
}

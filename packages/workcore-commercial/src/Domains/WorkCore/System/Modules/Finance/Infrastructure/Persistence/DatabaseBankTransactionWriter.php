<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\BankTransactionWriter;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Integrations\Banking\BankImportRow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class DatabaseBankTransactionWriter implements BankTransactionWriter
{
    public function __construct(private readonly IdentifierGenerator $ids) {}
    public function storeIfNew(string $companyId, string $bankAccountId, string $importId, BankImportRow $row): bool
    {
        try {
            DB::table('tm_bank_transactions')->insert([
                'id'=>$this->ids->uuid(),'company_id'=>$companyId,'bank_account_id'=>$bankAccountId,'transaction_date'=>$row->transactionDate->format('Y-m-d'),
                'observed_at'=>null,'amount_minor'=>$row->amount->minorAmount,'currency_code'=>$row->amount->currency->value,'description'=>$row->description,
                'reference_text'=>$row->referenceText,'payer_payee_hint'=>$row->payerPayeeHint,'transaction_hash'=>$row->transactionHash,'state'=>'unmatched',
                'source_metadata'=>json_encode(['import_id'=>$importId,'source_row'=>$row->sourceRow], JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now(),
            ]);
            return true;
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique') || str_contains(strtolower($e->getMessage()), 'duplicate')) return false;
            throw $e;
        }
    }
}

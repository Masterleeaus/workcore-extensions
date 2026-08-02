<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\DocumentNumberGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\JournalRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\JournalEntry;
use App\Domains\WorkCore\System\Modules\Finance\Numbering\SequenceKey;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabaseJournalRepository implements JournalRepository
{
    public function __construct(private readonly IdentifierGenerator $ids,private readonly DocumentNumberGenerator $numbers) {}
    public function post(JournalEntry $entry,ActionContext $context): string
    {
        $actor=$context->actor??throw new RuntimeException('Audit actor required.'); $id=$this->ids->uuid(); $currency=$entry->debitTotal()->currency->value;
        DB::table('tm_journal_entries')->insert(['id'=>$id,'company_id'=>$entry->companyId,'journal_number'=>$this->numbers->next($entry->companyId,SequenceKey::Journal,$entry->accountingDate),'accounting_date'=>$entry->accountingDate->format('Y-m-d'),
            'source_type'=>$entry->sourceType,'source_id'=>$entry->sourceId,'state'=>'posted','currency_code'=>$currency,'debit_total_minor'=>$entry->debitTotal()->minorAmount,'credit_total_minor'=>$entry->creditTotal()->minorAmount,'description'=>$entry->description,
            'created_by_actor_type'=>$actor->type->value,'created_by_actor_id'=>$actor->id,'origin'=>$context->origin->value,'correlation_id'=>$context->correlationId,'created_at'=>now(),'updated_at'=>now()]);
        foreach($entry->lines as $line) DB::table('tm_journal_lines')->insert(['id'=>$this->ids->uuid(),'company_id'=>$entry->companyId,'journal_entry_id'=>$id,'account_role'=>$line->accountRole->value,'side'=>$line->side->value,'amount_minor'=>$line->amount->minorAmount,
            'currency_code'=>$line->amount->currency->value,'memo'=>$line->memo,'workcore_job_id'=>$line->dimensions['workcore_job_id']??null,'workcore_customer_id'=>$line->dimensions['workcore_customer_id']??null,'dimensions'=>$line->dimensions?json_encode($line->dimensions,JSON_THROW_ON_ERROR):null,'created_at'=>now(),'updated_at'=>now()]); return $id;
    }
    public function existsForSource(string $companyId,string $sourceType,string $sourceId): bool { return DB::table('tm_journal_entries')->where('company_id',$companyId)->where('source_type',$sourceType)->where('source_id',$sourceId)->exists(); }
}

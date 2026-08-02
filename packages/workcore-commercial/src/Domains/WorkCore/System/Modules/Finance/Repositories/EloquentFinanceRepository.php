<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinanceRepositoryContract;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\{InvoiceLifecycle, InvoiceState, QuoteLifecycle, QuoteState};
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentFinanceRepository extends TenantScopedRepository implements FinanceRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private readonly QuoteLifecycle $quoteLifecycle,
        private readonly InvoiceLifecycle $invoiceLifecycle,
    ) {
        parent::__construct($db, $tenant);
    }

    public function create(array $data): string
    {
        return (string) ($this->createInvoice($data, $this->companyId(), $this->actorId())['id'] ?? '');
    }

    public function findById(string $id): ?array
    {
        $row = $this->db->table('tm_invoices')->where('company_id', (string) $this->companyId())->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public function update(string $id, array $data): bool
    {
        $data['updated_at'] = now();
        return $this->db->table('tm_invoices')->where('company_id', (string) $this->companyId())->where('id', $id)->update($data) > 0;
    }

    public function createQuote(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        [$lines, $totals, $currency] = $this->normaliseLines($data);
        $id = (string) Str::uuid();
        $versionId = (string) Str::uuid();
        $number = $this->number($data, 'quote_number', 'Q');
        $snapshot = $this->snapshotPayload($data, $lines, $totals, $currency);

        return $this->db->transaction(function () use ($data, $lines, $totals, $currency, $id, $versionId, $number, $snapshot, $companyId, $actorId): array {
            $now = now();
            $this->db->table('tm_quotes')->insert(array_merge([
                'id' => $id,
                'company_id' => (string) $companyId,
                'workcore_customer_id' => $this->requiredString($data, 'workcore_customer_id', 'customer_id'),
                'workcore_site_id' => $this->optionalString($data, 'workcore_site_id', 'site_id'),
                'workcore_job_id' => $this->optionalString($data, 'workcore_job_id', 'job_id'),
                'source_estimate_id' => $this->optionalString($data, 'source_estimate_id'),
                'quote_number' => $number,
                'current_version' => 1,
                'state' => 'draft',
                'currency_code' => $currency,
                'valid_until' => $data['valid_until'] ?? null,
                'sent_at' => null, 'accepted_at' => null, 'declined_at' => null, 'converted_at' => null,
                'converted_invoice_id' => null,
                'lock_version' => 0,
            ], $this->audit($actorId, $now)));
            $this->db->table('tm_quote_versions')->insert(array_merge([
                'id' => $versionId, 'company_id' => (string) $companyId, 'quote_id' => $id, 'version' => 1,
                'subtotal_minor' => $totals['subtotal_minor'], 'discount_minor' => $totals['discount_minor'],
                'tax_minor' => $totals['tax_minor'], 'total_minor' => $totals['total_minor'],
                'currency_code' => $currency, 'prices_include_tax' => (bool) ($data['prices_include_tax'] ?? true),
                'scope' => $data['scope'] ?? null, 'terms' => $data['terms'] ?? null,
                'customer_snapshot' => $this->json($data['customer_snapshot'] ?? []),
                'seller_snapshot' => $this->json($data['seller_snapshot'] ?? []),
                'snapshot_hash' => hash('sha256', $snapshot),
            ], $this->audit($actorId, $now)));
            $this->insertLines('tm_quote_lines', 'quote_version_id', $versionId, $companyId, $lines, $currency);
            return $this->quoteProfile($id, $companyId);
        }, 3);
    }

    public function transitionQuote(string $id, string $state, array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        return $this->db->transaction(function () use ($id, $state, $data, $companyId, $actorId): array {
            $quote = $this->locked('tm_quotes', $id, $companyId);
            $from = QuoteState::tryFrom((string) $quote->state);
            $to = QuoteState::tryFrom($state);
            if (! $from || ! $to || ! $this->quoteLifecycle->canTransition($from, $to)) {
                throw new InvalidArgumentException('Quote transition is not permitted.');
            }
            $changes = ['state' => $to->value, 'lock_version' => (int) $quote->lock_version + 1, 'updated_at' => now(), 'updated_by_user_id' => (string) $actorId, 'updated_by_actor_id' => (string) $actorId];
            if ($to === QuoteState::Sent) $changes['sent_at'] = $data['sent_at'] ?? now();
            if ($to === QuoteState::Accepted) $changes['accepted_at'] = $data['accepted_at'] ?? now();
            if ($to === QuoteState::Declined) $changes['declined_at'] = $data['declined_at'] ?? now();
            if ($to === QuoteState::Converted) { $changes['converted_at'] = $data['converted_at'] ?? now(); $changes['converted_invoice_id'] = $data['converted_invoice_id'] ?? null; }
            $this->db->table('tm_quotes')->where('id', $id)->where('company_id', (string) $companyId)->update($changes);
            return $this->quoteProfile($id, $companyId);
        }, 3);
    }

    public function createInvoice(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        [$lines, $totals, $currency] = $this->normaliseLines($data);
        $id = (string) Str::uuid();
        $number = $this->number($data, 'invoice_number', 'INV');
        $snapshot = $this->snapshotPayload($data, $lines, $totals, $currency);
        return $this->db->transaction(function () use ($data, $lines, $totals, $currency, $id, $number, $snapshot, $companyId, $actorId): array {
            $now = now();
            $this->db->table('tm_invoices')->insert(array_merge([
                'id' => $id, 'company_id' => (string) $companyId,
                'workcore_customer_id' => $this->requiredString($data, 'workcore_customer_id', 'customer_id'),
                'workcore_site_id' => $this->optionalString($data, 'workcore_site_id', 'site_id'),
                'workcore_job_id' => $this->optionalString($data, 'workcore_job_id', 'job_id'),
                'source_type' => $data['source_type'] ?? null, 'source_id' => $data['source_id'] ?? null,
                'source_key' => $data['source_key'] ?? null, 'source_quote_id' => $this->optionalString($data, 'source_quote_id', 'quote_id'),
                'invoice_number' => $number, 'state' => 'draft', 'currency_code' => $currency,
                'issue_date' => $data['issue_date'] ?? null, 'due_date' => $data['due_date'] ?? null,
                'subtotal_minor' => $totals['subtotal_minor'], 'discount_minor' => $totals['discount_minor'],
                'tax_minor' => $totals['tax_minor'], 'total_minor' => $totals['total_minor'],
                'allocated_minor' => 0, 'credited_minor' => 0, 'balance_minor' => $totals['total_minor'],
                'prices_include_tax' => (bool) ($data['prices_include_tax'] ?? true), 'draft_snapshot' => $snapshot,
                'approved_at' => null, 'issued_at' => null, 'delivered_at' => null, 'viewed_at' => null,
                'paid_at' => null, 'voided_at' => null, 'issued_snapshot_hash' => null, 'lock_version' => 0,
            ], $this->audit($actorId, $now)));
            $this->insertLines('tm_invoice_lines', 'invoice_id', $id, $companyId, $lines, $currency, true);
            return $this->invoiceProfile($id, $companyId);
        }, 3);
    }

    public function issueInvoice(string $id, array $data, int $companyId, int $actorId): array
    {
        $invoice = $this->locked('tm_invoices', $id, $companyId);
        if ((string) $invoice->state === 'draft') {
            $this->transitionInvoice($id, 'approved', $data, $companyId, $actorId);
        }
        return $this->transitionInvoice($id, 'issued', $data, $companyId, $actorId);
    }

    public function transitionInvoice(string $id, string $state, array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        return $this->db->transaction(function () use ($id, $state, $data, $companyId, $actorId): array {
            $invoice = $this->locked('tm_invoices', $id, $companyId);
            $from = InvoiceState::tryFrom((string) $invoice->state);
            $to = InvoiceState::tryFrom($state);
            if (! $from || ! $to || ! $this->invoiceLifecycle->canTransition($from, $to)) {
                throw new InvalidArgumentException('Invoice transition is not permitted.');
            }
            $changes = ['state' => $to->value, 'lock_version' => (int) $invoice->lock_version + 1, 'updated_at' => now(), 'updated_by_user_id' => (string) $actorId, 'updated_by_actor_id' => (string) $actorId];
            if ($to === InvoiceState::Approved) $changes['approved_at'] = $data['approved_at'] ?? now();
            if ($to === InvoiceState::Issued) { $changes['issued_at'] = $data['issued_at'] ?? now(); $changes['issue_date'] = $data['issue_date'] ?? now()->toDateString(); $changes['due_date'] = $data['due_date'] ?? $invoice->due_date; $changes['issued_snapshot_hash'] = hash('sha256', (string) $invoice->draft_snapshot); }
            if ($to === InvoiceState::Delivered) $changes['delivered_at'] = $data['delivered_at'] ?? now();
            if ($to === InvoiceState::Viewed) $changes['viewed_at'] = $data['viewed_at'] ?? now();
            if ($to === InvoiceState::Paid) $changes['paid_at'] = $data['paid_at'] ?? now();
            if ($to === InvoiceState::Voided) $changes['voided_at'] = $data['voided_at'] ?? now();
            $this->db->table('tm_invoices')->where('id', $id)->where('company_id', (string) $companyId)->update($changes);
            return $this->invoiceProfile($id, $companyId);
        }, 3);
    }

    public function createCreditNote(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId, $actorId);
        $invoiceId = $this->optionalString($data, 'invoice_id', 'invoice_public_id');
        $currency = $this->currency($data);
        $lines = $data['lines'] ?? [];
        if (! is_array($lines)) throw new InvalidArgumentException('Credit-note lines must be an array.');
        $amount = 0; $tax = 0;
        foreach ($lines as $line) { if (! is_array($line)) continue; $amount += max(0, (int) ($line['amount_minor'] ?? $line['line_total_minor'] ?? 0)); $tax += max(0, (int) ($line['tax_minor'] ?? 0)); }
        $amount = max($amount, (int) ($data['amount_minor'] ?? 0));
        if ($amount <= 0) throw new InvalidArgumentException('Credit-note amount must be positive.');
        $id = (string) Str::uuid(); $now = now();
        $this->db->transaction(function () use ($id, $invoiceId, $data, $currency, $amount, $tax, $lines, $companyId, $actorId, $now): void {
            $this->db->table('tm_credit_notes')->insert(array_merge([
                'id'=>$id,'company_id'=>(string)$companyId,'invoice_id'=>$invoiceId,
                'credit_note_number'=>$this->number($data,'credit_note_number','CN'),'state'=>(string)($data['state']??'issued'),
                'amount_minor'=>$amount,'tax_minor'=>$tax,'currency_code'=>$currency,'reason'=>(string)($data['reason']??'Adjustment'),
                'issued_at'=>$data['issued_at']??$now,
            ], $this->audit($actorId,$now)));
            foreach (array_values($lines) as $position=>$line) {
                if (!is_array($line)) continue;
                $this->db->table('tm_credit_note_lines')->insert([
                    'id'=>(string)Str::uuid(),'company_id'=>(string)$companyId,'credit_note_id'=>$id,'position'=>$position+1,
                    'description'=>(string)($line['description']??'Credit adjustment'),'amount_minor'=>max(0,(int)($line['amount_minor']??$line['line_total_minor']??0)),
                    'tax_minor'=>max(0,(int)($line['tax_minor']??0)),'currency_code'=>$currency,
                    'metadata'=>$this->json($line['metadata']??null),'created_at'=>$now,'updated_at'=>$now,
                ]);
            }
        },3);
        return $this->record('tm_credit_notes',$id,$companyId);
    }

    public function allocateCredit(string $creditNoteId, string $invoiceId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId,$actorId);
        return $this->db->transaction(function () use ($creditNoteId,$invoiceId,$data,$companyId,$actorId): array {
            $credit=$this->locked('tm_credit_notes',$creditNoteId,$companyId); $invoice=$this->locked('tm_invoices',$invoiceId,$companyId);
            if ((string)$credit->currency_code !== (string)$invoice->currency_code) throw new InvalidArgumentException('Credit-note currency does not match invoice currency.');
            $already=(int)$this->db->table('tm_credit_note_allocations')->where('company_id',(string)$companyId)->where('credit_note_id',$creditNoteId)->sum('amount_minor');
            $available=(int)$credit->amount_minor-$already; $amount=(int)($data['amount_minor']??min($available,(int)$invoice->balance_minor));
            if($amount<=0||$amount>$available||$amount>(int)$invoice->balance_minor) throw new InvalidArgumentException('Credit allocation amount is invalid.');
            $id=(string)Str::uuid(); $now=now();
            $this->db->table('tm_credit_note_allocations')->insert(['id'=>$id,'company_id'=>(string)$companyId,'credit_note_id'=>$creditNoteId,'invoice_id'=>$invoiceId,'amount_minor'=>$amount,'currency_code'=>(string)$credit->currency_code,'allocated_by_actor_type'=>'user','allocated_by_actor_id'=>(string)$actorId,'correlation_id'=>(string)($data['correlation_id']??Str::uuid()),'allocated_at'=>$data['allocated_at']??$now,'created_at'=>$now,'updated_at'=>$now]);
            $credited=(int)$invoice->credited_minor+$amount; $balance=max(0,(int)$invoice->total_minor-(int)$invoice->allocated_minor-$credited);
            $this->db->table('tm_invoices')->where('id',$invoiceId)->update(['credited_minor'=>$credited,'balance_minor'=>$balance,'state'=>$balance===0?'credited':(string)$invoice->state,'updated_at'=>$now]);
            return ['allocation'=>$this->record('tm_credit_note_allocations',$id,$companyId),'invoice'=>$this->invoiceProfile($invoiceId,$companyId)];
        },3);
    }

    public function recordExpense(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId,$actorId); $subtotal=max(0,(int)($data['subtotal_minor']??0)); $tax=max(0,(int)($data['tax_minor']??0)); $total=(int)($data['total_minor']??($subtotal+$tax));
        if($total<=0) throw new InvalidArgumentException('Expense total must be positive.');
        $id=(string)Str::uuid(); $now=now();
        $this->db->table('tm_expenses')->insert(array_merge(['id'=>$id,'company_id'=>(string)$companyId,'supplier_id'=>$this->optionalString($data,'supplier_id'),'workcore_job_id'=>$this->optionalString($data,'workcore_job_id','job_id'),'category_key'=>(string)($data['category_key']??$data['category']??'general'),'state'=>'draft','expense_date'=>$data['expense_date']??$data['incurred_on']??now()->toDateString(),'description'=>(string)($data['description']??'Expense'),'subtotal_minor'=>$subtotal,'tax_minor'=>$tax,'total_minor'=>$total,'currency_code'=>$this->currency($data),'receipt_required'=>(bool)($data['receipt_required']??true),'submitted_at'=>$data['submitted_at']??null,'approved_at'=>null,'approval_id'=>null],$this->audit($actorId,$now)));
        return $this->record('tm_expenses',$id,$companyId);
    }

    public function approveExpense(string $id, array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId,$actorId); $expense=$this->locked('tm_expenses',$id,$companyId);
        if((string)$expense->state!=='draft' && (string)$expense->state!=='submitted') throw new InvalidArgumentException('Only draft or submitted expenses may be approved.');
        $this->db->table('tm_expenses')->where('id',$id)->update(['state'=>'approved','approved_at'=>$data['approved_at']??now(),'approval_id'=>$data['approval_id']??null,'updated_at'=>now(),'updated_by_user_id'=>(string)$actorId,'updated_by_actor_id'=>(string)$actorId]);
        return $this->record('tm_expenses',$id,$companyId);
    }

    public function allocateReceivable(string $invoiceId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId,$actorId);
        return $this->db->transaction(function () use ($invoiceId,$data,$companyId,$actorId): array {
            $invoice=$this->locked('tm_invoices',$invoiceId,$companyId); $paymentId=$this->requiredString($data,'payment_id','source_id'); $payment=$this->locked('tm_payments',$paymentId,$companyId);
            if((string)$payment->currency_code!==(string)$invoice->currency_code) throw new InvalidArgumentException('Payment currency does not match invoice currency.');
            $available=(int)$payment->amount_minor-(int)$payment->allocated_minor; $amount=(int)($data['amount_minor']??min($available,(int)$invoice->balance_minor));
            if($amount<=0||$amount>$available||$amount>(int)$invoice->balance_minor) throw new InvalidArgumentException('Receivable allocation amount is invalid.');
            $id=(string)Str::uuid(); $now=now();
            $this->db->table('tm_payment_allocations')->insert(['id'=>$id,'company_id'=>(string)$companyId,'payment_id'=>$paymentId,'target_type'=>'invoice','target_id'=>$invoiceId,'amount_minor'=>$amount,'currency_code'=>(string)$invoice->currency_code,'allocated_by_actor_type'=>'user','allocated_by_actor_id'=>(string)$actorId,'approval_id'=>$data['approval_id']??null,'correlation_id'=>(string)($data['correlation_id']??Str::uuid()),'allocated_at'=>$data['allocated_at']??$now,'created_at'=>$now,'updated_at'=>$now]);
            $paymentAllocated=(int)$payment->allocated_minor+$amount; $invoiceAllocated=(int)$invoice->allocated_minor+$amount; $balance=max(0,(int)$invoice->total_minor-$invoiceAllocated-(int)$invoice->credited_minor);
            $this->db->table('tm_payments')->where('id',$paymentId)->update(['allocated_minor'=>$paymentAllocated,'state'=>$paymentAllocated>=(int)$payment->amount_minor?'allocated':'partially_allocated','updated_at'=>$now]);
            $this->db->table('tm_invoices')->where('id',$invoiceId)->update(['allocated_minor'=>$invoiceAllocated,'balance_minor'=>$balance,'state'=>$balance===0?'paid':'partially_paid','paid_at'=>$balance===0?$now:null,'updated_at'=>$now]);
            return ['allocation'=>$this->record('tm_payment_allocations',$id,$companyId),'invoice'=>$this->invoiceProfile($invoiceId,$companyId)];
        },3);
    }

    public function createAccount(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId,$actorId); $code=strtoupper(trim((string)($data['account_code']??$data['code']??''))); $name=trim((string)($data['name']??'')); $type=strtolower(trim((string)($data['type']??''))); $role=trim((string)($data['role']??strtolower($code)));
        if($code===''||$name===''||$role===''||!in_array($type,['asset','liability','equity','income','expense'],true)) throw new InvalidArgumentException('Valid account code, role, name and type are required.');
        $id=(string)Str::uuid(); $this->db->table('tm_accounts')->insert(['id'=>$id,'company_id'=>(string)$companyId,'account_code'=>$code,'name'=>$name,'role'=>$role,'type'=>$type,'currency_code'=>$data['currency_code']??null,'is_control_account'=>(bool)($data['is_control_account']??false),'is_active'=>(bool)($data['is_active']??true),'metadata'=>$this->json($data['metadata']??null),'created_at'=>now(),'updated_at'=>now()]);
        return $this->record('tm_accounts',$id,$companyId);
    }

    public function createAccountingPeriod(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId,$actorId); $starts=(string)($data['starts_on']??''); $ends=(string)($data['ends_on']??'');
        if($starts===''||$ends===''||$starts>$ends) throw new InvalidArgumentException('Valid accounting period dates are required.');
        $id=(string)Str::uuid(); $this->db->table('tm_accounting_periods')->insert(['id'=>$id,'company_id'=>(string)$companyId,'period_key'=>(string)($data['period_key']??$data['name']??($starts.'_'.$ends)),'starts_on'=>$starts,'ends_on'=>$ends,'state'=>'open','closed_at'=>null,'closed_by_actor_type'=>null,'closed_by_actor_id'=>null,'approval_id'=>null,'created_at'=>now(),'updated_at'=>now()]);
        return $this->record('tm_accounting_periods',$id,$companyId);
    }

    public function closeAccountingPeriod(string $id, array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId,$actorId); $period=$this->locked('tm_accounting_periods',$id,$companyId); if((string)$period->state!=='open') throw new InvalidArgumentException('Only open periods may be closed.');
        $this->db->table('tm_accounting_periods')->where('id',$id)->update(['state'=>'closed','closed_at'=>$data['closed_at']??now(),'closed_by_actor_type'=>'user','closed_by_actor_id'=>(string)$actorId,'approval_id'=>$data['approval_id']??null,'updated_at'=>now()]);
        return $this->record('tm_accounting_periods',$id,$companyId);
    }

    public function postJournal(array $data, int $companyId, int $actorId): array
    {
        $this->guard($companyId,$actorId); $lines=$data['lines']??null; if(!is_array($lines)||count($lines)<2) throw new InvalidArgumentException('At least two journal lines are required.');
        $debit=0;$credit=0;$currency=$this->currency($data);
        foreach($lines as $line){if(!is_array($line))throw new InvalidArgumentException('Invalid journal line.');$amount=(int)($line['amount_minor']??0);if($amount<=0)throw new InvalidArgumentException('Journal amounts must be positive.');$side=(string)($line['side']??'');if($side==='debit')$debit+=$amount;elseif($side==='credit')$credit+=$amount;else throw new InvalidArgumentException('Journal side must be debit or credit.');if(isset($line['currency_code'])&&strtoupper((string)$line['currency_code'])!==$currency)throw new InvalidArgumentException('Journal currencies must match.');}
        if($debit!==$credit) throw new InvalidArgumentException('Journal debits and credits must balance.');
        $periodId=$this->optionalString($data,'accounting_period_id'); if($periodId){$period=$this->locked('tm_accounting_periods',$periodId,$companyId);if((string)$period->state!=='open')throw new InvalidArgumentException('Accounting period is closed.');}
        $id=(string)Str::uuid();$now=now();$correlation=(string)($data['correlation_id']??Str::uuid());
        $this->db->transaction(function()use($id,$data,$companyId,$actorId,$lines,$debit,$credit,$currency,$periodId,$now,$correlation):void{$this->db->table('tm_journal_entries')->insert(['id'=>$id,'company_id'=>(string)$companyId,'journal_number'=>$this->number($data,'journal_number','JRN'),'accounting_date'=>$data['accounting_date']??now()->toDateString(),'accounting_period_id'=>$periodId,'source_type'=>(string)($data['source_type']??'manual'),'source_id'=>(string)($data['source_id']??$id),'state'=>'posted','currency_code'=>$currency,'debit_total_minor'=>$debit,'credit_total_minor'=>$credit,'description'=>$data['description']??null,'reversal_of_entry_id'=>$data['reversal_of_entry_id']??null,'created_by_actor_type'=>'user','created_by_actor_id'=>(string)$actorId,'origin'=>(string)($data['origin']??'workcore'),'correlation_id'=>$correlation,'created_at'=>$now,'updated_at'=>$now]);foreach($lines as $line){$this->db->table('tm_journal_lines')->insert(['id'=>(string)Str::uuid(),'company_id'=>(string)$companyId,'journal_entry_id'=>$id,'account_id'=>$line['account_id']??null,'account_role'=>(string)($line['account_role']??$line['role']??'unassigned'),'side'=>(string)$line['side'],'amount_minor'=>(int)$line['amount_minor'],'currency_code'=>$currency,'memo'=>$line['memo']??null,'workcore_job_id'=>$line['workcore_job_id']??null,'workcore_customer_id'=>$line['workcore_customer_id']??null,'dimensions'=>$this->json($line['dimensions']??null),'created_at'=>$now,'updated_at'=>$now]);}},3);
        return $this->record('tm_journal_entries',$id,$companyId);
    }

    public function quoteProfile(string $id, int $companyId): array
    {
        $quote=$this->record('tm_quotes',$id,$companyId); $version=$this->db->table('tm_quote_versions')->where('company_id',(string)$companyId)->where('quote_id',$id)->orderByDesc('version')->first();
        return ['quote'=>$quote,'version'=>$version?(array)$version:null,'lines'=>$version?$this->db->table('tm_quote_lines')->where('company_id',(string)$companyId)->where('quote_version_id',(string)$version->id)->orderBy('position')->get()->map(fn($r)=>(array)$r)->all():[],'id'=>$id,'public_id'=>$id];
    }

    public function invoiceProfile(string $id, int $companyId): array
    {
        $invoice=$this->record('tm_invoices',$id,$companyId);
        return ['invoice'=>$invoice,'lines'=>$this->db->table('tm_invoice_lines')->where('company_id',(string)$companyId)->where('invoice_id',$id)->orderBy('position')->get()->map(fn($r)=>(array)$r)->all(),'id'=>$id,'public_id'=>$id,'state'=>$invoice['state']??null];
    }

    public function financeSummary(int $companyId): array
    {
        $company=(string)$companyId;
        return ['quotes'=>['count'=>(int)$this->db->table('tm_quotes')->where('company_id',$company)->count(),'open'=>(int)$this->db->table('tm_quotes')->where('company_id',$company)->whereNotIn('state',['declined','expired','converted'])->count()],'invoices'=>['count'=>(int)$this->db->table('tm_invoices')->where('company_id',$company)->count(),'outstanding_minor'=>(int)$this->db->table('tm_invoices')->where('company_id',$company)->sum('balance_minor'),'overdue'=>(int)$this->db->table('tm_invoices')->where('company_id',$company)->where('state','overdue')->count()],'expenses'=>['count'=>(int)$this->db->table('tm_expenses')->where('company_id',$company)->count(),'total_minor'=>(int)$this->db->table('tm_expenses')->where('company_id',$company)->where('state','approved')->sum('total_minor')]];
    }

    public function searchReceivables(int $companyId, array $filters, int $perPage = 25): mixed
    {
        $query=$this->db->table('tm_invoices')->where('company_id',(string)$companyId)->where('balance_minor','>',0);
        if($state=trim((string)($filters['state']??'')))$query->where('state',$state);
        if($customer=trim((string)($filters['workcore_customer_id']??$filters['customer_id']??'')))$query->where('workcore_customer_id',$customer);
        if(isset($filters['due_before']))$query->whereDate('due_date','<=',(string)$filters['due_before']);
        return $query->orderBy('due_date')->paginate(max(1,min(100,$perPage)));
    }

    private function guard(int $companyId,int $actorId):void{if($companyId<1||$actorId<1)throw new InvalidArgumentException('Company and actor are required.');$this->assertCompany($companyId);}
    private function locked(string $table,string $id,int $companyId):object{$row=$this->db->table($table)->where('company_id',(string)$companyId)->where('id',$id)->lockForUpdate()->first();if(!$row)throw new InvalidArgumentException('Finance record was not found.');return $row;}
    private function record(string $table,string $id,int $companyId):array{$row=$this->db->table($table)->where('company_id',(string)$companyId)->where('id',$id)->first();if(!$row)throw new InvalidArgumentException('Finance record was not found.');$result=(array)$row;$result['public_id']=$result['id']??$id;return $result;}
    private function audit(int $actorId,mixed $now):array{return ['created_by_user_id'=>(string)$actorId,'updated_by_user_id'=>(string)$actorId,'created_by_actor_type'=>'user','created_by_actor_id'=>(string)$actorId,'updated_by_actor_type'=>'user','updated_by_actor_id'=>(string)$actorId,'origin'=>'workcore','correlation_id'=>(string)Str::uuid(),'created_at'=>$now,'updated_at'=>$now];}
    private function currency(array $data):string{$value=strtoupper(trim((string)($data['currency_code']??$data['currency']??'AUD')));if(strlen($value)!==3)throw new InvalidArgumentException('A three-letter currency code is required.');return $value;}
    private function number(array $data,string $key,string $prefix):string{$value=trim((string)($data[$key]??''));return $value!==''?$value:$prefix.'-'.now()->format('YmdHis').'-'.strtoupper(substr(str_replace('-','',(string)Str::uuid()),0,6));}
    private function requiredString(array $data,string ...$keys):string{foreach($keys as $key){$value=trim((string)($data[$key]??''));if($value!=='')return $value;}throw new InvalidArgumentException($keys[0].' is required.');}
    private function optionalString(array $data,string ...$keys):?string{foreach($keys as $key){$value=$data[$key]??null;if(is_string($value)&&trim($value)!=='')return trim($value);}return null;}
    private function json(mixed $value):?string{return $value===null?null:json_encode($value,JSON_THROW_ON_ERROR);}
    private function snapshotPayload(array $data,array $lines,array $totals,string $currency):string{return json_encode(['input'=>$data,'lines'=>$lines,'totals'=>$totals,'currency_code'=>$currency],JSON_THROW_ON_ERROR);}
    private function normaliseLines(array $data):array{$input=$data['lines']??[];if(!is_array($input)||$input===[])throw new InvalidArgumentException('At least one finance line is required.');$currency=$this->currency($data);$lines=[];$totals=['subtotal_minor'=>0,'discount_minor'=>0,'tax_minor'=>0,'total_minor'=>0];foreach(array_values($input) as $position=>$line){if(!is_array($line))throw new InvalidArgumentException('Finance lines must be objects.');$scale=max(0,min(6,(int)($line['quantity_scale']??2)));$quantityScaled=(int)($line['quantity_scaled']??round((float)($line['quantity']??1)*(10**$scale)));$unit=(int)($line['unit_price_minor']??0);if($quantityScaled<=0||$unit<0)throw new InvalidArgumentException('Line quantity and price are invalid.');$base=(int)round($quantityScaled*$unit/(10**$scale));$discount=max(0,(int)($line['discount_minor']??0));$rate=max(0,(int)($line['tax_rate_basis_points']??0));$tax=max(0,(int)($line['tax_minor']??round(max(0,$base-$discount)*$rate/10000)));$total=max(0,(int)($line['line_total_minor']??($base-$discount+$tax)));$row=['position'=>$position+1,'catalogue_item_id'=>$line['catalogue_item_id']??null,'source_type'=>$line['source_type']??null,'source_id'=>$line['source_id']??null,'description'=>(string)($line['description']??'Line item'),'quantity_scaled'=>$quantityScaled,'quantity_scale'=>$scale,'unit_price_minor'=>$unit,'discount_minor'=>$discount,'tax_code'=>$line['tax_code']??null,'tax_rate_basis_points'=>$rate,'tax_minor'=>$tax,'line_total_minor'=>$total,'metadata'=>$line['metadata']??null];$lines[]=$row;$totals['subtotal_minor']+=$base;$totals['discount_minor']+=$discount;$totals['tax_minor']+=$tax;$totals['total_minor']+=$total;}return[$lines,$totals,$currency];}
    private function insertLines(string $table,string $parentColumn,string $parentId,int $companyId,array $lines,string $currency,bool $invoice=false):void{$now=now();foreach($lines as $line){$row=['id'=>(string)Str::uuid(),'company_id'=>(string)$companyId,$parentColumn=>$parentId,'position'=>$line['position'],'description'=>$line['description'],'quantity_scaled'=>$line['quantity_scaled'],'quantity_scale'=>$line['quantity_scale'],'unit_price_minor'=>$line['unit_price_minor'],'discount_minor'=>$line['discount_minor'],'tax_code'=>$line['tax_code'],'tax_rate_basis_points'=>$line['tax_rate_basis_points'],'tax_minor'=>$line['tax_minor'],'line_total_minor'=>$line['line_total_minor'],'currency_code'=>$currency,'metadata'=>$this->json($line['metadata']),'created_at'=>$now,'updated_at'=>$now];if($invoice){$row['source_type']=$line['source_type'];$row['source_id']=$line['source_id'];}else{$row['catalogue_item_id']=$line['catalogue_item_id'];}$this->db->table($table)->insert($row);}}
}

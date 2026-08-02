<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Persistence;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\InvoiceRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceDraft;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceLineDraft;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceState;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabaseInvoiceRepository implements InvoiceRepository
{
    public function __construct(private readonly IdentifierGenerator $ids) {}

    public function findById(string $companyId, string $invoiceId): ?InvoiceRecord
    {
        $row = DB::table('tm_invoices')->where('company_id',$companyId)->where('id',$invoiceId)->first();
        return $row ? $this->hydrate($row) : null;
    }
    public function findBySourceKey(string $companyId, string $sourceKey): ?InvoiceRecord
    {
        $row = DB::table('tm_invoices')->where('company_id',$companyId)->where('source_key',$sourceKey)->first();
        return $row ? $this->hydrate($row) : null;
    }
    public function create(InvoiceDraft $draft, ActionContext $context): InvoiceRecord
    {
        $actor=$context->actor ?? throw new RuntimeException('Audit actor required.'); $now=now();
        DB::table('tm_invoices')->insert([
            'id'=>$draft->id,'company_id'=>$draft->companyId,'workcore_customer_id'=>$draft->customerId,'workcore_site_id'=>$draft->siteId,'workcore_job_id'=>$draft->jobId,
            'source_type'=>$draft->sourceType,'source_id'=>$draft->sourceId,'source_key'=>$draft->sourceKey,'invoice_number'=>$draft->invoiceNumber,'state'=>'draft','currency_code'=>$draft->currencyCode(),
            'issue_date'=>$draft->issueDate->format('Y-m-d'),'due_date'=>$draft->dueDate->format('Y-m-d'),'subtotal_minor'=>$draft->subtotal->minorAmount,'discount_minor'=>$draft->discount->minorAmount,
            'tax_minor'=>$draft->tax->minorAmount,'total_minor'=>$draft->total->minorAmount,'balance_minor'=>$draft->total->minorAmount,'prices_include_tax'=>$draft->pricesIncludeTax,'draft_snapshot'=>json_encode($draft->snapshotPayload(),JSON_THROW_ON_ERROR),
            'created_by_user_id'=>$context->userId,'updated_by_user_id'=>$context->userId,'created_by_actor_type'=>$actor->type->value,'created_by_actor_id'=>$actor->id,
            'updated_by_actor_type'=>$actor->type->value,'updated_by_actor_id'=>$actor->id,'origin'=>$context->origin->value,'correlation_id'=>$context->correlationId,'created_at'=>$now,'updated_at'=>$now,
        ]);
        foreach($draft->lines as $line) $this->insertLine($draft,$line,$now);
        return new InvoiceRecord($draft);
    }
    public function issue(string $companyId,string $invoiceId,string $snapshotHash,DateTimeImmutable $issuedAt,ActionContext $context): InvoiceRecord
    {
        $actor=$context->actor ?? throw new RuntimeException('Audit actor required.');
        $updated=DB::table('tm_invoices')->where('company_id',$companyId)->where('id',$invoiceId)->where('state','draft')->update([
            'state'=>'issued','issued_at'=>$issuedAt,'issued_snapshot_hash'=>$snapshotHash,'updated_by_user_id'=>$context->userId,'updated_by_actor_type'=>$actor->type->value,'updated_by_actor_id'=>$actor->id,
            'origin'=>$context->origin->value,'correlation_id'=>$context->correlationId,'lock_version'=>DB::raw('lock_version + 1'),'updated_at'=>now(),
        ]);
        if($updated!==1) throw new RuntimeException('Invoice could not be issued from its current state.');
        return $this->findById($companyId,$invoiceId) ?? throw new RuntimeException('Issued invoice not found.');
    }
    public function appendEvent(string $companyId,string $invoiceId,string $eventType,array $payload,DateTimeImmutable $occurredAt,ActionContext $context): void
    {
        $actor=$context->actor ?? throw new RuntimeException('Audit actor required.');
        DB::table('tm_invoice_events')->insert(['id'=>$this->ids->uuid(),'company_id'=>$companyId,'invoice_id'=>$invoiceId,'event_type'=>$eventType,'actor_type'=>$actor->type->value,'actor_id'=>$actor->id,
            'origin'=>$context->origin->value,'correlation_id'=>$context->correlationId,'payload'=>$payload?json_encode($payload,JSON_THROW_ON_ERROR):null,'occurred_at'=>$occurredAt,'created_at'=>now(),'updated_at'=>now()]);
    }
    private function insertLine(InvoiceDraft $draft,InvoiceLineDraft $line,mixed $now): void
    {
        DB::table('tm_invoice_lines')->insert(['id'=>$line->id,'company_id'=>$draft->companyId,'invoice_id'=>$draft->id,'position'=>$line->position,'source_type'=>$line->sourceType,'source_id'=>$line->sourceId,
            'description'=>$line->description,'quantity_scaled'=>$line->quantityScaled,'quantity_scale'=>$line->quantityScale,'unit_price_minor'=>$line->unitPrice->minorAmount,'discount_minor'=>$line->discount->minorAmount,
            'tax_code'=>$line->taxCode,'tax_rate_basis_points'=>$line->taxRateBasisPoints,'tax_minor'=>$line->tax->minorAmount,'line_total_minor'=>$line->total->minorAmount,'currency_code'=>$line->total->currency->value,
            'metadata'=>$line->metadata?json_encode($line->metadata,JSON_THROW_ON_ERROR):null,'created_at'=>$now,'updated_at'=>$now]);
    }
    private function hydrate(object $row): InvoiceRecord
    {
        $lineRows=DB::table('tm_invoice_lines')->where('company_id',$row->company_id)->where('invoice_id',$row->id)->orderBy('position')->get();
        $lines=[]; foreach($lineRows as $l){ $currency=$l->currency_code; $discount=Money::ofMinor((int)$l->discount_minor,$currency); $tax=Money::ofMinor((int)$l->tax_minor,$currency); $total=Money::ofMinor((int)$l->line_total_minor,$currency); $subtotal=$total->add($discount)->subtract($tax);
            $lines[]=new InvoiceLineDraft($l->id,(int)$l->position,$l->description,(int)$l->quantity_scaled,(int)$l->quantity_scale,Money::ofMinor((int)$l->unit_price_minor,$currency),$subtotal,$discount,$tax,$total,$l->source_type,$l->source_id,$l->tax_code,(int)$l->tax_rate_basis_points,$l->metadata?json_decode($l->metadata,true,512,JSON_THROW_ON_ERROR):[]); }
        $currency=$row->currency_code; $snapshot=$row->draft_snapshot?json_decode($row->draft_snapshot,true,512,JSON_THROW_ON_ERROR):[]; $draft=new InvoiceDraft($row->id,$row->company_id,$row->workcore_customer_id,$row->workcore_site_id,$row->workcore_job_id,$row->source_type??'unknown',$row->source_id??$row->id,$row->source_key??('invoice:'.$row->id),$row->invoice_number,
            new DateTimeImmutable((string)$row->issue_date),new DateTimeImmutable((string)$row->due_date),Money::ofMinor((int)$row->subtotal_minor,$currency),Money::ofMinor((int)$row->discount_minor,$currency),Money::ofMinor((int)$row->tax_minor,$currency),Money::ofMinor((int)$row->total_minor,$currency),$lines,(bool)$row->prices_include_tax,is_array($snapshot['customer_snapshot']??null)?$snapshot['customer_snapshot']:['id'=>$row->workcore_customer_id],is_array($snapshot['seller_snapshot']??null)?$snapshot['seller_snapshot']:['company_id'=>$row->company_id]);
        return new InvoiceRecord($draft,InvoiceState::from($row->state),$row->issued_at?new DateTimeImmutable((string)$row->issued_at):null,$row->issued_snapshot_hash);
    }
}

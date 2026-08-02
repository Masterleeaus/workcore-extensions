<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Services;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AutomationScheduler;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinancialExceptionRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\JournalRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\OutboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ReceivableRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ReceiptRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TransactionManager;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Events\OutboxEvent;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\PostingTemplates\PaymentConfirmedPostingTemplate;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentEvidenceRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\ReceiptRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Receivables\ReceivableAccount;
use RuntimeException;

final class PaymentConfirmationService
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly ReceivableRepository $receivables,
        private readonly JournalRepository $journals,
        private readonly PaymentConfirmedPostingTemplate $posting,
        private readonly AutomationScheduler $scheduler,
        private readonly ReceiptRepository $receipts,
        private readonly FinancialExceptionRepository $exceptions,
        private readonly OutboxRepository $outbox,
        private readonly Clock $clock,
        private readonly IdentifierGenerator $ids,
        private readonly TransactionManager $transactions,
    ) {}

    /** @return array<string,mixed> */
    public function confirmAndAllocate(PaymentEvidenceRecord $evidence,ReceivableAccount $receivable,ActionContext $context): array
    {
        return $this->transactions->run(function() use($evidence,$receivable,$context): array {
            $existing=$this->payments->findByEvidence($context->companyId,$evidence->id);
            if($existing!==null)return ['payment'=>$existing->toArray(),'invoice'=>$receivable,'receipt_id'=>null,'cancelled_actions'=>0,'reused'=>true];
            if($evidence->amount->currency->value!==$receivable->balance->currency->value)throw new RuntimeException('Payment and receivable currencies must match.');
            $allocation=min($evidence->amount->minorAmount,$receivable->balance->minorAmount);
            $payment=$this->payments->createConfirmed($evidence,$receivable,$allocation,$context);
            $updated=$this->receivables->allocate($context->companyId,$receivable->invoiceId,$allocation,$context);
            $this->journals->post($this->posting->build($context->companyId,$payment->id,$this->clock->now(),$payment->amount,$allocation),$context);
            $cancelled=$updated->balance->minorAmount===0?$this->scheduler->cancelForEntity($context->companyId,'invoice',$receivable->invoiceId,$context):0;
            $receipt=new ReceiptRecord($this->ids->uuid(),$context->companyId,$payment->id,$receivable->invoiceId,'RCPT-'.strtoupper(substr(str_replace('-','',$payment->id),-8)),$payment->amount,$this->clock->now());
            $this->receipts->create($receipt,$context);
            foreach([
                ['money.payment.confirmed','payment',$payment->id,['payment'=>$payment->toArray()]],
                ['money.payment.allocated','invoice',$receivable->invoiceId,['payment_id'=>$payment->id,'allocated_minor'=>$allocation,'balance_minor'=>$updated->balance->minorAmount]],
                ['money.receipt.issued','receipt',$receipt->id,['payment_id'=>$payment->id,'invoice_id'=>$receivable->invoiceId,'receipt_number'=>$receipt->receiptNumber]],
                ['money.collection.reminders_updated','invoice',$receivable->invoiceId,['cancelled_actions'=>$cancelled,'balance_minor'=>$updated->balance->minorAmount]],
            ] as [$type,$aggregateType,$aggregateId,$payload])$this->outbox->append(new OutboxEvent($this->ids->uuid(),$context->companyId,$type,$aggregateType,$aggregateId,$payload,$this->clock->now(),$context));
            if($payment->unallocatedMinor>0)$this->exceptions->create('payment_overallocation_credit_required','medium','payment',$payment->id,['unallocated_minor'=>$payment->unallocatedMinor,'currency_code'=>$payment->amount->currency->value,'customer_id'=>$receivable->customerId],$context);
            return ['payment'=>$payment->toArray(),'invoice'=>['id'=>$updated->invoiceId,'state'=>$updated->state,'balance_minor'=>$updated->balance->minorAmount,'currency_code'=>$updated->balance->currency->value],'receipt_id'=>$receipt->id,'cancelled_actions'=>$cancelled,'reused'=>false];
        });
    }
}

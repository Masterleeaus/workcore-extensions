<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Services;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\RiskLevel;
use App\Domains\WorkCore\System\Modules\Finance\Automation\AutomationDecision;
use App\Domains\WorkCore\System\Modules\Finance\Automation\FinancialActionType;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AutomationPolicyRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\DocumentSnapshotRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\InvoiceRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\JournalRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\OutboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Documents\FinancialDocumentSnapshot;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Commercial\InvoiceRecord;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Events\OutboxEvent;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Ledger\PostingTemplates\InvoiceIssuedPostingTemplate;
use RuntimeException;

final class InvoiceIssuer
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly AutomationPolicyRepository $policies,
        private readonly DocumentSnapshotRepository $snapshots,
        private readonly JournalRepository $journals,
        private readonly InvoiceIssuedPostingTemplate $posting,
        private readonly OutboxRepository $outbox,
        private readonly InvoiceCollectionScheduler $collections,
        private readonly IdentifierGenerator $ids,
        private readonly Clock $clock,
    ) {
    }

    /** @return array{invoice:InvoiceRecord,decision:AutomationDecision,scheduled_action_ids:list<string>} */
    public function issueWhenAllowed(InvoiceRecord $invoice, ActionContext $context): array
    {
        $policy = $this->policies->forCompany($context->companyId, $invoice->draft->total->currency);
        $decision = $policy->decide(FinancialActionType::IssueInvoice, RiskLevel::Medium, $invoice->draft->total);
        if ($decision !== AutomationDecision::Execute && $context->approvalId === null) {
            return ['invoice' => $invoice, 'decision' => $decision, 'scheduled_action_ids' => []];
        }

        $actor = $context->actor ?? throw new RuntimeException('Financial actions require an explicit audit actor.');
        $now = $this->clock->now();
        $snapshot = FinancialDocumentSnapshot::capture(
            $context->companyId,
            'invoice',
            $invoice->id(),
            1,
            $invoice->draft->snapshotPayload(),
            $now,
        );
        $this->snapshots->store($snapshot, $actor);
        if (! $this->journals->existsForSource($context->companyId, 'invoice', $invoice->id())) {
            $journal = $this->posting->build(
                $context->companyId,
                $invoice->id(),
                $invoice->draft->issueDate,
                $invoice->draft->subtotal->subtract($invoice->draft->discount),
                $invoice->draft->tax,
            );
            $this->journals->post($journal, $context);
        }
        $issued = $this->invoices->issue($context->companyId, $invoice->id(), $snapshot->payloadHash, $now, $context);
        $this->invoices->appendEvent($context->companyId, $invoice->id(), 'invoice.issued', [], $now, $context);
        $this->outbox->append(new OutboxEvent(
            $this->ids->uuid(),
            $context->companyId,
            'money.invoice.issued',
            'invoice',
            $invoice->id(),
            ['invoice' => $issued->toArray()],
            $now,
            $context,
        ));
        $scheduled = $this->collections->schedule($issued, $context);
        return ['invoice' => $issued, 'decision' => AutomationDecision::Execute, 'scheduled_action_ids' => $scheduled];
    }
}

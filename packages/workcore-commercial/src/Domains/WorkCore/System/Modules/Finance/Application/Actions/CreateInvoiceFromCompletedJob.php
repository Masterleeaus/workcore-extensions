<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Actions;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionResult;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ApprovalRequirement;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\CreateInvoiceFromCompletedJobInput;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\GenerativeUiEnvelope;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\RiskAssessment;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\ApprovalMode;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\ResultStatus;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\RiskLevel;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\SurfaceType;
use App\Domains\WorkCore\System\Modules\Finance\Application\Services\InvoiceIssuer;
use App\Domains\WorkCore\System\Modules\Finance\Application\Services\PaymentRequestService;
use App\Domains\WorkCore\System\Modules\Finance\Application\Services\WorkCoreJobInvoiceAssembler;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\FinanceAccessGate;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\TitanMoneyPermission;
use App\Domains\WorkCore\System\Modules\Finance\Automation\AutomationDecision;
use App\Domains\WorkCore\System\Modules\Finance\Automation\Invoicing\InvoiceEligibilityEvaluator;
use App\Domains\WorkCore\System\Modules\Finance\Automation\Invoicing\InvoiceEligibilityInput;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AuditRecorder;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdempotencyStore;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\InvoiceRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\OutboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TitanMoneyAction;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TransactionManager;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\WorkCoreCommercialSource;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Events\OutboxEvent;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\IdempotencyClaimStatus;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\IdempotencyKey;
use App\Domains\WorkCore\System\Modules\Finance\Idempotency\RequestFingerprint;
use RuntimeException;

final class CreateInvoiceFromCompletedJob implements TitanMoneyAction
{
    private const ACTION_KEY = 'money.invoice.create_from_job';

    public function __construct(
        private readonly FinanceAccessGate $access,
        private readonly WorkCoreCommercialSource $workCore,
        private readonly InvoiceEligibilityEvaluator $eligibility,
        private readonly WorkCoreJobInvoiceAssembler $assembler,
        private readonly InvoiceRepository $invoices,
        private readonly InvoiceIssuer $issuer,
        private readonly PaymentRequestService $paymentRequests,
        private readonly IdempotencyStore $idempotency,
        private readonly TransactionManager $transactions,
        private readonly OutboxRepository $outbox,
        private readonly AuditRecorder $audit,
        private readonly Clock $clock,
        private readonly \App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator $ids,
    ) {
    }

    public function authorize(ActionContext $context): void
    {
        $this->access->assertAllowed($context, TitanMoneyPermission::InvoicesDraft->value);
    }

    public function assessRisk(ActionInput $input, ActionContext $context): RiskAssessment
    {
        $values = $this->requireInput($input);
        $level = $values->issueWhenPolicyAllows ? RiskLevel::Medium : RiskLevel::Low;
        return new RiskAssessment($level, ['Creates a customer financial document from completed work.'], null);
    }

    public function execute(ActionInput $input, ActionContext $context): ActionResult
    {
        $values = $this->requireInput($input);
        if ($context->actor === null) {
            throw new RuntimeException('Financial actions require an explicit audit actor.');
        }
        $this->authorize($context);
        if ($values->issueWhenPolicyAllows) {
            $this->access->assertAllowed($context, TitanMoneyPermission::InvoicesIssue->value);
        }
        if ($values->sendPaymentInstructions) {
            $this->access->assertAllowed($context, TitanMoneyPermission::PaymentInstructionsSend->value);
            $this->access->assertAllowed($context, TitanMoneyPermission::PaymentQrGenerate->value);
        }

        $key = IdempotencyKey::fromRaw($context->idempotencyKey);
        $fingerprint = RequestFingerprint::fromPayload($values->toArray());
        $claim = $this->idempotency->claim($context->companyId, self::ACTION_KEY, $key, $fingerprint);
        if ($claim->status === IdempotencyClaimStatus::Replay) {
            return $this->resultFromData($claim->resultPayload ?? [], true);
        }
        if ($claim->status === IdempotencyClaimStatus::InProgress) {
            return new ActionResult(ResultStatus::PendingApproval, 'money.invoice.creation_in_progress', [], null, [], null);
        }
        if ($claim->status === IdempotencyClaimStatus::Conflict) {
            return new ActionResult(ResultStatus::Failed, 'money.invoice.idempotency_conflict', [], null, [], null);
        }

        try {
            $data = $this->transactions->run(function () use ($values, $context, $key): array {
                $sourceKey = 'workcore_job:' . $values->jobId;
                $existing = $this->invoices->findBySourceKey($context->companyId, $sourceKey);
                if ($existing !== null) {
                    $wasIssued = $existing->state->value === 'issued';
                    $issue = [
                        'invoice' => $existing,
                        'decision' => $wasIssued ? AutomationDecision::Execute : AutomationDecision::Propose,
                        'scheduled_action_ids' => [],
                    ];
                    if (! $wasIssued && $values->issueWhenPolicyAllows) {
                        $issue = $this->issuer->issueWhenAllowed($existing, $context);
                    }

                    $invoice = $issue['invoice'];
                    $payment = null;
                    if (! $wasIssued && $values->sendPaymentInstructions && $invoice->state->value === 'issued') {
                        $payment = $this->paymentRequests->createAndDeliver(
                            $invoice,
                            $values->preferredPaymentMethod,
                            $values->deliveryChannels,
                            $context,
                        );
                    }

                    $data = [
                        'invoice' => $invoice->toArray(),
                        'reused' => true,
                        'issued' => $invoice->state->value === 'issued',
                        'automation_decision' => $issue['decision']->value,
                        'scheduled_action_ids' => $issue['scheduled_action_ids'],
                        'payment' => $payment,
                    ];
                    $this->idempotency->complete($context->companyId, self::ACTION_KEY, $key, $data);
                    $this->audit->record(self::ACTION_KEY, $wasIssued ? 'reused' : 'resumed', $context, $context->actor, $data);
                    return $data;
                }

                $job = $this->workCore->completedJobForCompany($context->companyId, $values->jobId);
                if ($job === null) {
                    throw new RuntimeException('Completed WorkCore job was not found for the active company.');
                }
                $lines = $this->workCore->billableLinesForJob($context->companyId, $values->jobId);
                $check = $this->eligibility->evaluate(new InvoiceEligibilityInput(
                    jobId: $values->jobId,
                    completed: (bool) ($job['completed'] ?? false),
                    billable: (bool) ($job['billable'] ?? false),
                    existingInvoice: false,
                    customerAvailable: is_string($job['customer_id'] ?? null) && trim((string) $job['customer_id']) !== '',
                    billableLines: count($lines),
                    evidenceComplete: (bool) ($job['evidence_complete'] ?? false),
                ));
                if (! $check->eligible) {
                    throw new RuntimeException('Invoice is not eligible: ' . implode(',', $check->reasons));
                }

                $draft = $this->assembler->assemble($context->companyId, $job, $lines, $this->clock->now());
                $invoice = $this->invoices->create($draft, $context);
                $this->invoices->appendEvent($context->companyId, $invoice->id(), 'invoice.created', ['job_id' => $values->jobId], $this->clock->now(), $context);
                $this->outbox->append(new OutboxEvent(
                    $this->ids->uuid(),
                    $context->companyId,
                    'money.invoice.created',
                    'invoice',
                    $invoice->id(),
                    ['invoice' => $invoice->toArray(), 'workcore_job_id' => $values->jobId],
                    $this->clock->now(),
                    $context,
                ));

                $issue = ['invoice' => $invoice, 'decision' => AutomationDecision::Propose, 'scheduled_action_ids' => []];
                if ($values->issueWhenPolicyAllows) {
                    $issue = $this->issuer->issueWhenAllowed($invoice, $context);
                    $invoice = $issue['invoice'];
                }

                $payment = null;
                if ($values->sendPaymentInstructions && $invoice->state->value === 'issued') {
                    $payment = $this->paymentRequests->createAndDeliver(
                        $invoice,
                        $values->preferredPaymentMethod,
                        $values->deliveryChannels,
                        $context,
                    );
                }

                $data = [
                    'invoice' => $invoice->toArray(),
                    'reused' => false,
                    'issued' => $invoice->state->value === 'issued',
                    'automation_decision' => $issue['decision']->value,
                    'scheduled_action_ids' => $issue['scheduled_action_ids'],
                    'payment' => $payment,
                ];
                $this->idempotency->complete($context->companyId, self::ACTION_KEY, $key, $data);
                $this->audit->record(self::ACTION_KEY, 'success', $context, $context->actor, $data);
                return $data;
            });

            return $this->resultFromData($data, false);
        } catch (\Throwable $error) {
            $this->idempotency->fail($context->companyId, self::ACTION_KEY, $key, 'invoice_creation_failed');
            if ($context->actor !== null) {
                $this->audit->record(self::ACTION_KEY, 'failure', $context, $context->actor, ['error' => $error->getMessage()]);
            }
            return new ActionResult(ResultStatus::Failed, 'money.invoice.creation_failed', [], null, [['message' => $error->getMessage()]], null);
        }
    }

    private function requireInput(ActionInput $input): CreateInvoiceFromCompletedJobInput
    {
        if (! $input instanceof CreateInvoiceFromCompletedJobInput) {
            throw new \InvalidArgumentException('CreateInvoiceFromCompletedJobInput is required.');
        }
        return $input;
    }

    /** @param array<string,mixed> $data */
    private function resultFromData(array $data, bool $replayed): ActionResult
    {
        $issued = (bool) ($data['issued'] ?? false);
        $decision = (string) ($data['automation_decision'] ?? 'execute');
        $approval = null;
        if (! $issued && $decision === AutomationDecision::RequestApproval->value) {
            $approval = new ApprovalRequirement(ApprovalMode::ExplicitApproval, true, 'Issue and send invoice', ['invoice_id']);
        }
        $ui = new GenerativeUiEnvelope(
            '1.0',
            'money.invoice-created-card',
            $data + ['replayed' => $replayed],
            [SurfaceType::Workspace, SurfaceType::MobileCard, SurfaceType::TabletPanel],
            $issued
                ? [['key' => 'money.invoice.open', 'label' => 'Open invoice']]
                : [['key' => 'money.invoice.approve_issue', 'label' => 'Approve issue']],
        );
        return new ActionResult(
            $approval ? ResultStatus::PendingApproval : ResultStatus::Success,
            $replayed ? 'money.invoice.creation_replayed' : 'money.invoice.created_from_job',
            $data,
            $ui,
            [],
            $approval,
        );
    }
}

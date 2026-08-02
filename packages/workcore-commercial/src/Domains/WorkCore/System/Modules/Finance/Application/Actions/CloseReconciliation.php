<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Actions;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionResult;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\CloseReconciliationInput;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\RiskAssessment;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\ResultStatus;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\RiskLevel;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\FinanceAccessGate;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\TitanMoneyPermission;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AuditRecorder;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\OutboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ReconciliationRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TitanMoneyAction;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TransactionManager;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Events\OutboxEvent;
use RuntimeException;

final class CloseReconciliation implements TitanMoneyAction
{
    public const ACTION_KEY = 'money.reconciliation.close';

    public function __construct(
        private readonly FinanceAccessGate $access,
        private readonly ReconciliationRepository $repository,
        private readonly Clock $clock,
        private readonly AuditRecorder $audit,
        private readonly OutboxRepository $outbox,
        private readonly IdentifierGenerator $ids,
        private readonly TransactionManager $transactions,
    ) {}

    public function authorize(ActionContext $context): void
    {
        $this->access->assertAllowed($context, TitanMoneyPermission::ReconciliationManage);
    }

    public function assessRisk(ActionInput $input, ActionContext $context): RiskAssessment
    {
        return new RiskAssessment(RiskLevel::High, ['Closes a financial reconciliation period.'], null);
    }

    public function execute(ActionInput $input, ActionContext $context): ActionResult
    {
        if (! $input instanceof CloseReconciliationInput) {
            throw new \InvalidArgumentException('CloseReconciliationInput is required.');
        }

        $this->authorize($context);
        $session = $this->repository->get($context->companyId, $input->reconciliationId);

        if ($session === null) {
            return $this->result(ResultStatus::Failed, 'money.reconciliation.not_found', [], 'Reconciliation was not found in the active company.');
        }
        if ($session->state === 'closed') {
            return $this->result(ResultStatus::Success, 'money.reconciliation.already_closed', ['reconciliation_id' => $session->id]);
        }

        $balance = $session->balance();
        if (! $balance->canClose()) {
            return $this->result(
                ResultStatus::Failed,
                'money.reconciliation.not_balanced',
                ['balance' => $balance->toArray()],
                'Statement and reconciled closing balances must match.',
            );
        }
        if ($session->unresolvedLineCount > 0) {
            return $this->result(
                ResultStatus::Failed,
                'money.reconciliation.unresolved_lines',
                ['unresolved_line_count' => $session->unresolvedLineCount],
                'Every reconciliation line must be confirmed or ignored.',
            );
        }

        $actor = $context->actor ?? throw new RuntimeException('Audit actor required.');
        $closedAt = $this->clock->now();
        $data = $this->transactions->run(function () use ($context, $session, $actor, $closedAt): array {
            $this->repository->close($context->companyId, $session->id, $context, $closedAt);
            $payload = [
                'reconciliation_id' => $session->id,
                'bank_account_id' => $session->bankAccountId,
                'statement_closing_balance_minor' => $session->statementClosingBalanceMinor,
                'reconciled_closing_balance_minor' => $session->reconciledClosingBalanceMinor,
                'currency_code' => $session->currencyCode,
                'closed_at' => $closedAt->format(DATE_ATOM),
            ];
            $this->audit->record(self::ACTION_KEY, 'money.reconciliation.closed', $context, $actor, $payload);
            $this->outbox->append(new OutboxEvent(
                $this->ids->uuid(),
                $context->companyId,
                'money.reconciliation.closed',
                'reconciliation',
                $session->id,
                $payload,
                $closedAt,
                $context,
            ));
            return $payload;
        });

        return $this->result(ResultStatus::Success, 'money.reconciliation.closed', $data);
    }

    /** @param array<string,mixed> $data */
    private function result(ResultStatus $status, string $code, array $data, ?string $message = null): ActionResult
    {
        return new ActionResult($status, $code, $data, null, $message === null ? [] : [['message' => $message]], null);
    }
}

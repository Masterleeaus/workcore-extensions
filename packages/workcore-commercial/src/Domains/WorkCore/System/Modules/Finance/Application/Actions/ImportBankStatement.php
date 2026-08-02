<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\Actions;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionResult;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\GenerativeUiEnvelope;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ImportBankStatementInput;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\RiskAssessment;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\ResultStatus;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\RiskLevel;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\SurfaceType;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\FinanceAccessGate;
use App\Domains\WorkCore\System\Modules\Finance\Authorization\TitanMoneyPermission;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\AuditRecorder;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\OutboxRepository;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\TitanMoneyAction;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Events\OutboxEvent;
use App\Domains\WorkCore\System\Modules\Finance\Integrations\Banking\BankStatementImportService;
use RuntimeException;

final class ImportBankStatement implements TitanMoneyAction
{
    public const ACTION_KEY = 'money.bank_statement.import';

    public function __construct(
        private readonly FinanceAccessGate $access,
        private readonly BankStatementImportService $service,
        private readonly AuditRecorder $audit,
        private readonly OutboxRepository $outbox,
        private readonly Clock $clock,
        private readonly IdentifierGenerator $ids,
    ) {}

    public function authorize(ActionContext $context): void
    {
        $this->access->assertAllowed($context, TitanMoneyPermission::ReconciliationManage);
    }

    public function assessRisk(ActionInput $input, ActionContext $context): RiskAssessment
    {
        return new RiskAssessment(RiskLevel::Medium, ['Imports external bank transaction evidence.'], null);
    }

    public function execute(ActionInput $input, ActionContext $context): ActionResult
    {
        if (! $input instanceof ImportBankStatementInput) throw new \InvalidArgumentException('ImportBankStatementInput is required.');
        $this->authorize($context);
        $actor = $context->actor ?? throw new RuntimeException('Audit actor required.');
        $now = $this->clock->now();
        $result = $this->service->import(
            $context->companyId,
            $input->bankAccountId,
            $input->csv,
            $input->currencyCode,
            $context,
            $now,
            hash('sha256', $input->csv),
        );
        $data = [
            'import_id' => $result->importId,
            'bank_account_id' => $input->bankAccountId,
            'filename' => $input->filename,
            'imported_count' => $result->importedCount,
            'duplicate_count' => $result->duplicateCount,
            'error_count' => count($result->errors),
            'errors' => $result->errors,
        ];
        $code = $result->errors === [] ? 'money.bank_statement.imported' : 'money.bank_statement.imported_with_errors';
        $this->audit->record(self::ACTION_KEY, $code, $context, $actor, $data);
        $this->outbox->append(new OutboxEvent(
            $this->ids->uuid(),
            $context->companyId,
            'money.bank_statement.imported',
            'bank_import',
            $result->importId,
            $data,
            $now,
            $context,
        ));
        return new ActionResult(
            ResultStatus::Success,
            $code,
            $data,
            new GenerativeUiEnvelope('1.0', 'money.bank-import-card', $data, [SurfaceType::Workspace, SurfaceType::TabletPanel, SurfaceType::Headless], []),
            [],
            null,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\PaymentEvidenceRecord;

interface PaymentEvidenceRepository
{
    public function findById(string $companyId, string $evidenceId): ?PaymentEvidenceRecord;
    public function findByHash(string $companyId, string $rawPayloadHash): ?PaymentEvidenceRecord;
    public function save(PaymentEvidenceRecord $record, ActionContext $context): PaymentEvidenceRecord;
    public function markVerified(string $companyId, string $evidenceId, int $confidenceScore, string $status): void;
}

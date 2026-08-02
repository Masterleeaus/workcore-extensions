<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\TrustAccounting\Contracts;

interface TrustAccountingRepositoryContract
{
    public function createTrustAccount(array $data, int $companyId, int $actorId): array;
    public function createMatter(array $data, int $companyId, int $actorId): array;
    public function recordReceipt(array $data, int $companyId, int $actorId): array;
    public function allocateReceipt(string $receiptPublicId, string $matterPublicId, array $data, int $companyId, int $actorId): array;
    public function requestDisbursement(array $data, int $companyId, int $actorId): array;
    public function approveDisbursement(string $publicId, array $data, int $companyId, int $actorId): array;
    public function releaseDisbursement(string $publicId, array $data, int $companyId, int $actorId): array;
    public function reverseLedgerEntry(string $publicId, array $data, int $companyId, int $actorId): array;
    public function trustSummary(int $companyId, array $filters = []): array;
    public function matterLedger(string $matterPublicId, int $companyId): array;
}

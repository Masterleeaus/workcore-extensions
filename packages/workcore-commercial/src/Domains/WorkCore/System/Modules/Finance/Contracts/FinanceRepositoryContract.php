<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

interface FinanceRepositoryContract
{
    public function create(array $data): string;
    public function findById(string $id): ?array;
    public function update(string $id, array $data): bool;

    public function createQuote(array $data, int $companyId, int $actorId): array;
    public function transitionQuote(string $id, string $state, array $data, int $companyId, int $actorId): array;
    public function createInvoice(array $data, int $companyId, int $actorId): array;
    public function issueInvoice(string $id, array $data, int $companyId, int $actorId): array;
    public function transitionInvoice(string $id, string $state, array $data, int $companyId, int $actorId): array;
    public function createCreditNote(array $data, int $companyId, int $actorId): array;
    public function allocateCredit(string $creditNoteId, string $invoiceId, array $data, int $companyId, int $actorId): array;
    public function recordExpense(array $data, int $companyId, int $actorId): array;
    public function approveExpense(string $id, array $data, int $companyId, int $actorId): array;
    public function allocateReceivable(string $invoiceId, array $data, int $companyId, int $actorId): array;
    public function createAccount(array $data, int $companyId, int $actorId): array;
    public function createAccountingPeriod(array $data, int $companyId, int $actorId): array;
    public function closeAccountingPeriod(string $id, array $data, int $companyId, int $actorId): array;
    public function postJournal(array $data, int $companyId, int $actorId): array;
    public function quoteProfile(string $id, int $companyId): array;
    public function invoiceProfile(string $id, int $companyId): array;
    public function financeSummary(int $companyId): array;
    public function searchReceivables(int $companyId, array $filters, int $perPage = 25): mixed;
}

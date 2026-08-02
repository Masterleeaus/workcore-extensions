<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Actions;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\FinanceRepositoryContract;

final class GetInvoiceProfile
{
    public function __construct(private FinanceRepositoryContract $repository) {}
    public function __invoke(array $filters, int $companyId, int $perPage = 25): mixed
    {
        $id = trim((string) ($filters['invoice_id'] ?? $filters['invoice_public_id'] ?? '')); if ($id === '') throw new \InvalidArgumentException('invoice_id is required.'); return $this->repository->invoiceProfile($id, $companyId);
    }
}

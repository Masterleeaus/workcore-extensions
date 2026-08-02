<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Supply\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SupplyRepositoryContract
{
    public function searchSuppliers(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function searchPurchaseOrders(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function createCategory(array $data, int $companyId): array;
    public function createSupplier(array $data, int $companyId, int $actorId): array;
    public function rateSupplier(string $supplierPublicId, array $data, int $companyId, int $actorId): array;
    public function createPurchaseOrder(array $data, int $companyId, int $actorId): array;
    public function approvePurchaseOrder(string $purchaseOrderPublicId, int $companyId, int $actorId): array;
    public function receiveGoods(array $data, int $companyId, int $actorId): array;
    public function createTransfer(array $data, int $companyId, int $actorId): array;
    public function dispatchTransfer(string $transferPublicId, int $companyId, int $actorId): array;
    public function receiveTransfer(string $transferPublicId, int $companyId, int $actorId): array;
    public function createStockCount(array $data, int $companyId, int $actorId): array;
    public function completeStockCount(string $stockCountPublicId, array $data, int $companyId, int $actorId): array;
    public function createAdjustment(array $data, int $companyId, int $actorId): array;
    public function postAdjustment(string $adjustmentPublicId, int $companyId, int $actorId): array;
}

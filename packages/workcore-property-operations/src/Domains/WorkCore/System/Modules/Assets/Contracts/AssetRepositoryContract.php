<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AssetRepositoryContract
{
    public function search(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function createCategory(array $data, int $companyId, int $actorId): array;
    public function createAsset(array $data, int $companyId, int $actorId): array;
    public function assignAsset(string $assetPublicId, array $data, int $companyId, int $actorId): array;
    public function checkoutAsset(string $assetPublicId, array $data, int $companyId, int $actorId): array;
    public function checkinAsset(string $assetPublicId, array $data, int $companyId, int $actorId): array;
    public function transferAsset(string $assetPublicId, array $data, int $companyId, int $actorId): array;
    public function changeStatus(string $assetPublicId, array $data, int $companyId, int $actorId): array;
    public function recordCondition(string $assetPublicId, array $data, int $companyId, int $actorId): array;
    public function reportDamage(string $assetPublicId, array $data, int $companyId, int $actorId): array;
    public function createMaintenancePlan(string $assetPublicId, array $data, int $companyId, int $actorId): array;
    public function recordMaintenance(string $assetPublicId, array $data, int $companyId, int $actorId): array;
    public function startMaintenance(string $assetPublicId, string $maintenancePublicId, array $data, int $companyId, int $actorId): array;
    public function completeMaintenance(string $assetPublicId, string $maintenancePublicId, array $data, int $companyId, int $actorId): array;
    public function recordMeterReading(string $assetPublicId, array $data, int $companyId, int $actorId): array;
    public function recordCalibration(string $assetPublicId, array $data, int $companyId, int $actorId): array;
    public function createWarranty(string $assetPublicId, array $data, int $companyId, int $actorId): array;
    public function createWarrantyClaim(string $assetPublicId, string $warrantyPublicId, array $data, int $companyId, int $actorId): array;
    public function updateWarrantyClaim(string $assetPublicId, string $claimPublicId, array $data, int $companyId, int $actorId): array;
    public function registerIdentifier(string $assetPublicId, array $data, int $companyId, int $actorId): array;
    public function lookupIdentifier(string $value, ?string $type, int $companyId): ?array;
    public function linkDocument(string $assetPublicId, array $data, int $companyId, int $actorId): array;
    public function createFromSupplyReceipt(array $data, int $companyId, int $actorId): array;
    public function availability(string $assetPublicId, int $companyId): array;
    public function overdueCustody(int $companyId, array $filters = []): array;
}

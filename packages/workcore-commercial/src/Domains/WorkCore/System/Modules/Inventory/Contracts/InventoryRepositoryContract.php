<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Inventory\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InventoryRepositoryContract
{
    public function search(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function locations(int $companyId, array $filters = []): array;
    public function createItem(array $data, int $companyId, int $actorId): array;
    public function createLocation(array $data, int $companyId, int $actorId): array;
    public function recordMovement(string $itemPublicId, array $data, int $companyId, int $actorId): array;
    public function reserve(string $itemPublicId, array $data, int $companyId, int $actorId): array;
    public function consumeReservation(string $reservationPublicId, array $data, int $companyId, int $actorId): array;
    public function releaseReservation(string $reservationPublicId, array $data, int $companyId, int $actorId): array;
    public function setReorderRule(string $itemPublicId, array $data, int $companyId, int $actorId): array;
}

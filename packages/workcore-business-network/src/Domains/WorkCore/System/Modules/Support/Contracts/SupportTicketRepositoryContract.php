<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Support\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SupportTicketRepositoryContract
{
    public function search(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function createQueue(array $data, int $companyId, int $actorId): array;
    public function createTicket(array $data, int $companyId, int $actorId): array;
    public function addMessage(string $ticketPublicId, array $data, int $companyId, int $actorId): array;
    public function assign(string $ticketPublicId, array $data, int $companyId, int $actorId): array;
    public function changeStatus(string $ticketPublicId, array $data, int $companyId, int $actorId): array;
    public function findByPublicId(int $companyId, string $publicId): ?array;
    public function linkWorkOrder(string $ticketPublicId, string $workOrderPublicId, int $companyId, int $actorId): array;
}

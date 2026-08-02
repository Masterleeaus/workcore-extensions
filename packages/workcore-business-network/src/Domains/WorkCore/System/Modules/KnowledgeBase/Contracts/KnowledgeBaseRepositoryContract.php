<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface KnowledgeBaseRepositoryContract
{
    public function search(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function createCategory(array $data, int $companyId, int $actorId): array;
    public function createArticle(array $data, int $companyId, int $actorId): array;
    public function updateArticle(string $articlePublicId, array $data, int $companyId, int $actorId): array;
    public function changeStatus(string $articlePublicId, array $data, int $companyId, int $actorId): array;
    public function createFromSupportTicket(string $ticketPublicId, array $data, int $companyId, int $actorId): array;
    public function recordFeedback(string $articlePublicId, array $data, int $companyId, int $actorId): array;
    public function findByPublicId(int $companyId, string $publicId): ?array;
}

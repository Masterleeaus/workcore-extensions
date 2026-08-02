<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Documents\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DocumentRepositoryContract
{
    public function searchDocuments(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function searchEvidence(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function createDocument(array $data, int $companyId, int $actorId): array;
    public function addVersion(string $documentPublicId, array $data, int $companyId, int $actorId): array;
    public function linkDocument(string $documentPublicId, array $data, int $companyId, int $actorId): array;
    public function addComment(string $documentPublicId, array $data, int $companyId, int $actorId): array;
    public function requestApproval(string $documentPublicId, array $data, int $companyId, int $actorId): array;
    public function decideApproval(string $approvalPublicId, array $data, int $companyId, int $actorId): array;
    public function registerEvidence(array $data, int $companyId, int $actorId): array;
    public function signoffEvidence(string $evidencePublicId, array $data, int $companyId, int $actorId): array;
}

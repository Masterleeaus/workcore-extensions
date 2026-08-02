<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assurance\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AssuranceRepositoryContract
{
    public function searchInspections(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function searchFindings(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function searchRisks(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function searchIncidents(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function summary(int $companyId): array;
    public function createTemplate(array $data, int $companyId, int $actorId): array;
    public function startInspection(array $data, int $companyId, int $actorId): array;
    public function recordResult(string $inspectionPublicId, array $data, int $companyId, int $actorId): array;
    public function completeInspection(string $inspectionPublicId, array $data, int $companyId, int $actorId): array;
    public function createFinding(array $data, int $companyId, int $actorId): array;
    public function createCorrectiveAction(string $findingPublicId, array $data, int $companyId, int $actorId): array;
    public function updateCorrectiveAction(string $actionPublicId, array $data, int $companyId, int $actorId): array;
    public function recordRisk(array $data, int $companyId, int $actorId): array;
    public function recordIncident(array $data, int $companyId, int $actorId): array;
}

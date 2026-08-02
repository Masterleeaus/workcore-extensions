<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Forms\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FormRepositoryContract
{
    public function searchTemplates(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function searchSubmissions(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function createTemplate(array $data, int $companyId, int $actorId): array;
    public function publishTemplate(string $templatePublicId, int $companyId, int $actorId): array;
    public function startSubmission(array $data, int $companyId, int $actorId): array;
    public function saveResponse(string $submissionPublicId, array $data, int $companyId, int $actorId): array;
    public function submitSubmission(string $submissionPublicId, array $data, int $companyId, int $actorId): array;
    public function convertFailureToWorkOrder(string $failurePublicId, array $data, int $companyId, int $actorId): array;
}

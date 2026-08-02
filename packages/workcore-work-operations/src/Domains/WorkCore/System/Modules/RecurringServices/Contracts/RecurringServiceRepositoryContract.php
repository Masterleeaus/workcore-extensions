<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\RecurringServices\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RecurringServiceRepositoryContract
{
    public function search(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator;
    public function createAgreement(array $data, int $companyId, int $actorId): array;
    public function changeAgreementStatus(string $publicId, array $data, int $companyId, int $actorId): array;
    public function generate(?string $agreementPublicId, array $data, int $companyId, int $actorId): array;
    public function skipOccurrence(string $publicId, array $data, int $companyId, int $actorId): array;
    public function rescheduleOccurrence(string $publicId, array $data, int $companyId, int $actorId): array;
    public function renewAgreement(string $publicId, array $data, int $companyId, int $actorId): array;
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Catalogue\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ServiceCatalogueRepositoryContract
{
    public function searchServices(
        int $companyId,
        ?string $query = null,
        ?string $categoryPublicId = null,
        int $perPage = 25,
        ?string $businessLinePublicId = null,
    ): LengthAwarePaginator;

    public function createCategory(array $data, int $companyId, int $actorId): array;
    public function createService(array $data, int $companyId, int $actorId): array;
    public function createJobTemplate(array $data, int $companyId, int $actorId): array;

    public function listVariants(string $servicePublicId, int $companyId): array;
    public function createVariant(array $data, int $companyId, int $actorId): array;
    public function updateVariant(string $variantPublicId, array $data, int $companyId, int $actorId): array;
    public function deleteVariant(string $variantPublicId, int $companyId, int $actorId): array;

    public function listFaqs(string $servicePublicId, int $companyId): array;
    public function createFaq(array $data, int $companyId, int $actorId): array;
    public function updateFaq(string $faqPublicId, array $data, int $companyId, int $actorId): array;
    public function deleteFaq(string $faqPublicId, int $companyId, int $actorId): array;
}

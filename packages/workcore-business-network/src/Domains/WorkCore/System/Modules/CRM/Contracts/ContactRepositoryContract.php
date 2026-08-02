<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Contracts;

use App\Domains\WorkCore\System\Modules\CRM\Data\ContactData;

interface ContactRepositoryContract
{
    public function create(ContactData $data, int $companyId, int $actorId): array;
    public function setPrimary(int $companyId, string $contactPublicId, int $actorId): array;
    public function listForCustomer(int $companyId, string $customerPublicId): array;
}

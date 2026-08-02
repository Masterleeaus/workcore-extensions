<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Observations;

interface ObservationRepositoryContract
{
    public function save(IntelligenceObservation $observation, int $actorId, ?string $correlationId = null): IntelligenceObservation;

    /** @return list<array<string,mixed>> */
    public function listForCompany(int $companyId, ?string $domain = null, int $limit = 50): array;
}

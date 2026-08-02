<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Recommendations;

interface RecommendationRepositoryContract
{
    public function save(IntelligenceRecommendation $recommendation, int $actorId, ?string $correlationId = null): IntelligenceRecommendation;

    /** @return list<array<string,mixed>> */
    public function listForCompany(int $companyId, ?string $status = null, int $limit = 50): array;
}

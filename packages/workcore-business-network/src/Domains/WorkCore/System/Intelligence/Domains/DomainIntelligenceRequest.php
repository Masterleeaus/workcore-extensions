<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains;

use InvalidArgumentException;

final readonly class DomainIntelligenceRequest
{
    /**
     * @param array<string,string|int> $subjects
     * @param array<string,mixed> $filters
     */
    public function __construct(
        public int $companyId,
        public int $actorId,
        public array $subjects = [],
        public array $filters = [],
        public int $maxItems = 50,
    ) {
        if ($companyId < 1 || $actorId < 1) {
            throw new InvalidArgumentException('Company and actor identifiers are required.');
        }
        if ($maxItems < 1 || $maxItems > 500) {
            throw new InvalidArgumentException('Domain intelligence detail limit must be between 1 and 500.');
        }
        if (count($subjects) > 25 || count($filters) > 50) {
            throw new InvalidArgumentException('Domain intelligence scope is too broad.');
        }
    }
}

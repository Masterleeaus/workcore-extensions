<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Observations;

use InvalidArgumentException;

final readonly class IntelligenceObservation
{
    /** @param list<array<string,mixed>> $evidence @param list<string> $dataGaps */
    public function __construct(
        public string $publicId,
        public int $companyId,
        public string $domain,
        public string $type,
        public string $summary,
        public float $confidence,
        public array $evidence = [],
        public array $dataGaps = [],
        public string $status = 'observed',
    ) {
        if (trim($publicId) === '' || $companyId < 1 || trim($domain) === '' || trim($type) === '' || trim($summary) === '' || $confidence < 0.0 || $confidence > 1.0) {
            throw new InvalidArgumentException('Intelligence observation is invalid.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'public_id' => $this->publicId,
            'company_id' => $this->companyId,
            'domain' => $this->domain,
            'type' => $this->type,
            'summary' => $this->summary,
            'confidence' => $this->confidence,
            'evidence' => $this->evidence,
            'data_gaps' => $this->dataGaps,
            'status' => $this->status,
        ];
    }
}

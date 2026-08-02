<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Recommendations;

use InvalidArgumentException;

final readonly class IntelligenceRecommendation
{
    /** @param list<string> $successMetrics */
    public function __construct(
        public string $publicId,
        public int $companyId,
        public ?string $observationPublicId,
        public string $summary,
        public string $proposedAction,
        public float $confidence,
        public string $status = 'review_required',
        public array $successMetrics = [],
        public string $risk = 'medium',
    ) {
        if (trim($publicId) === '' || $companyId < 1 || trim($summary) === '' || trim($proposedAction) === '' || $confidence < 0.0 || $confidence > 1.0) {
            throw new InvalidArgumentException('Intelligence recommendation is invalid.');
        }
        if (! in_array($risk, ['low', 'medium', 'high', 'critical'], true)) {
            throw new InvalidArgumentException('Intelligence recommendation risk is invalid.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'public_id' => $this->publicId,
            'company_id' => $this->companyId,
            'observation_public_id' => $this->observationPublicId,
            'summary' => $this->summary,
            'proposed_action' => $this->proposedAction,
            'confidence' => $this->confidence,
            'status' => $this->status,
            'success_metrics' => $this->successMetrics,
            'risk' => $this->risk,
        ];
    }
}

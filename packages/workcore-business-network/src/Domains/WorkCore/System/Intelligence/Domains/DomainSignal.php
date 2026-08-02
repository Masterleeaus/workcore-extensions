<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains;

use InvalidArgumentException;

final readonly class DomainSignal
{
    /**
     * @param list<array<string,mixed>> $evidence
     * @param list<string> $dataGaps
     */
    public function __construct(
        public string $type,
        public string $severity,
        public string $summary,
        public float $confidence,
        public array $evidence,
        public ?string $suggestedAction = null,
        public array $dataGaps = [],
    ) {
        if (! preg_match('/^[a-z][a-z0-9_.]{2,159}$/', $type)) {
            throw new InvalidArgumentException('Domain signal type is invalid.');
        }
        if (! in_array($severity, ['info', 'low', 'medium', 'high', 'critical'], true)) {
            throw new InvalidArgumentException('Domain signal severity is invalid.');
        }
        if ($confidence < 0.0 || $confidence > 1.0 || trim($summary) === '' || $evidence === []) {
            throw new InvalidArgumentException('Domain signal summary, bounded confidence and evidence are required.');
        }
    }

    /**
     * @param list<string>|null $allowedActionKeys Null preserves the suggested action; an array filters it.
     * @return array<string,mixed>
     */
    public function toArray(?array $allowedActionKeys = null): array
    {
        $suggestedAction = $this->suggestedAction;
        if ($allowedActionKeys !== null && ($suggestedAction === null || ! in_array($suggestedAction, $allowedActionKeys, true))) {
            $suggestedAction = null;
        }

        return [
            'type' => $this->type,
            'severity' => $this->severity,
            'summary' => $this->summary,
            'confidence' => $this->confidence,
            'evidence' => $this->evidence,
            'suggested_action' => $suggestedAction,
            'data_gaps' => $this->dataGaps,
        ];
    }
}

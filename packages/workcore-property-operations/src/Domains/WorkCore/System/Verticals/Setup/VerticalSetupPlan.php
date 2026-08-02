<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Setup;

final readonly class VerticalSetupPlan
{
    /**
     * @param list<string> $addOns
     * @param list<array<string,mixed>> $businessLines
     * @param list<array<string,mixed>> $locales
     * @param array<string,bool> $features
     * @param array<string,string> $terminology
     * @param array<string,mixed> $settings
     * @param list<array<string,mixed>> $documents
     * @param array<string,mixed> $operations
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public string $primaryVertical,
        public array $addOns,
        public array $businessLines,
        public array $locales,
        public array $features,
        public array $terminology,
        public array $settings,
        public array $documents,
        public array $operations = [],
        public array $metadata = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'primary_vertical' => $this->primaryVertical,
            'add_ons' => $this->addOns,
            'business_lines' => $this->businessLines,
            'locales' => $this->locales,
            'features' => $this->features,
            'terminology' => $this->terminology,
            'settings' => $this->settings,
            'documents' => $this->documents,
            'operations' => $this->operations,
            'metadata' => $this->metadata,
        ];
    }
}

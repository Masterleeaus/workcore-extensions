<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Donors;

final readonly class DonorSourceDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $path,
        public string $targetModule,
        public string $capability,
        public int $fileCount,
        public int $conflictCount,
        public int $missingFromRepairedCount,
        public string $status = 'donor_only',
    ) {
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'path' => $this->path,
            'target_module' => $this->targetModule,
            'capability' => $this->capability,
            'file_count' => $this->fileCount,
            'conflict_count' => $this->conflictCount,
            'missing_from_repaired_count' => $this->missingFromRepairedCount,
            'status' => $this->status,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records;

use InvalidArgumentException;

final readonly class BusinessRecordSnapshot
{
    /** @param array<string,mixed> $attributes */
    public function __construct(
        public string $recordType,
        public string $publicId,
        public int $companyId,
        public array $attributes = [],
    ) {
        if ($recordType === '' || $publicId === '' || $companyId < 1) {
            throw new InvalidArgumentException('Record type, public ID and company ID are required.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'record_type' => $this->recordType,
            'public_id' => $this->publicId,
            'company_id' => $this->companyId,
            'attributes' => $this->attributes,
        ];
    }
}

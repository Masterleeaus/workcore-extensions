<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

use InvalidArgumentException;

final readonly class KnowledgeSourceDescriptor
{
    public function __construct(
        public string $type,
        public string $publicId,
        public string $title,
    ) {
        if (! preg_match('/^[a-z][a-z0-9_.:-]{0,79}$/', $type) || trim($publicId) === '' || trim($title) === '') {
            throw new InvalidArgumentException('Knowledge source descriptor is invalid.');
        }
    }

    public function stableDocumentId(int $companyId): string
    {
        if ($companyId < 1) {
            throw new InvalidArgumentException('Company is required for stable knowledge identity.');
        }

        return hash('sha256', $companyId . '|' . $this->type . '|' . $this->publicId);
    }
}

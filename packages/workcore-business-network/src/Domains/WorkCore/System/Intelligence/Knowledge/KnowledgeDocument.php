<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

use InvalidArgumentException;

final readonly class KnowledgeDocument
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public int $companyId,
        public string $documentId,
        public string $content,
        public string $visibility,
        public array $metadata = [],
        public string $title = '',
        public string $sourceType = 'manual',
        public ?string $sourcePublicId = null,
        public ?string $requiredPermission = null,
        public ?int $ownerUserId = null,
        public ?string $sourceUpdatedAt = null,
        public ?string $expiresAt = null,
        public ?int $createdByUserId = null,
    ) {
        if ($companyId < 1 || trim($documentId) === '' || trim($content) === '') {
            throw new InvalidArgumentException('Knowledge documents require company, identifier and content.');
        }

        if (! preg_match('/^[a-z][a-z0-9_.:-]{1,79}$/', $visibility)) {
            throw new InvalidArgumentException('Knowledge visibility is invalid.');
        }

        if ($visibility === 'private' && ($ownerUserId === null || $ownerUserId < 1)) {
            throw new InvalidArgumentException('Private knowledge requires an owning user.');
        }

        if ($ownerUserId !== null && $ownerUserId < 1) {
            throw new InvalidArgumentException('Knowledge owner is invalid.');
        }

        if ($createdByUserId !== null && $createdByUserId < 1) {
            throw new InvalidArgumentException('Knowledge creator is invalid.');
        }

        if (! preg_match('/^[a-z][a-z0-9_.:-]{0,79}$/', $sourceType)) {
            throw new InvalidArgumentException('Knowledge source type is invalid.');
        }

        if ($requiredPermission !== null && ! preg_match('/^[a-z][a-z0-9_.:-]{2,159}$/', $requiredPermission)) {
            throw new InvalidArgumentException('Knowledge permission key is invalid.');
        }

        foreach ([$sourceUpdatedAt, $expiresAt] as $timestamp) {
            if ($timestamp !== null && strtotime($timestamp) === false) {
                throw new InvalidArgumentException('Knowledge timestamps must be parseable.');
            }
        }
    }

    public function contentHash(): string
    {
        return hash('sha256', $this->content);
    }

    public function effectiveTitle(): string
    {
        $title = trim($this->title);

        return $title !== '' ? $title : $this->documentId;
    }
}

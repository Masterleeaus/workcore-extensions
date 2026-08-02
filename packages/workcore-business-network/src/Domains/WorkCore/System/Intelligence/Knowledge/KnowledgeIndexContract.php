<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

interface KnowledgeIndexContract
{
    /** @return array<string,mixed> */
    public function index(KnowledgeDocument $document): array;

    public function remove(int $companyId, string $documentId): void;

    public function search(KnowledgeQuery $query): KnowledgeRetrievalResult;
}

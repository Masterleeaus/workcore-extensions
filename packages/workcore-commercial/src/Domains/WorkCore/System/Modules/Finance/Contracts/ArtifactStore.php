<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

interface ArtifactStore
{
    public function store(
        string $companyId,
        string $category,
        string $filename,
        string $contentType,
        string $contents,
    ): string;
}

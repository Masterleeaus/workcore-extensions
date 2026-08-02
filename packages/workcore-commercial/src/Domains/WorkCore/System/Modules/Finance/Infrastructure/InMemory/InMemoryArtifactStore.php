<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\ArtifactStore;

final class InMemoryArtifactStore implements ArtifactStore
{
    /** @var array<string,array<string,string>> */ public array $artifacts = [];
    public function store(string $companyId, string $category, string $filename, string $contentType, string $contents): string
    {
        $id = hash('sha256', $companyId . '|' . $category . '|' . $filename . '|' . $contents);
        $this->artifacts[$id] = compact('companyId','category','filename','contentType','contents');
        return $id;
    }
}

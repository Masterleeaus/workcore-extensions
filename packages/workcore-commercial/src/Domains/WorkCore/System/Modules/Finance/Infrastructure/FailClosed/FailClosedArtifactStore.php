<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\ArtifactStore;
use RuntimeException;

final class FailClosedArtifactStore implements ArtifactStore
{
    public function store(string $companyId,string $category,string $filename,string $contentType,string $contents): string { throw new RuntimeException('Bind Titan Docs ArtifactStore before generating payment QR artifacts.'); }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\QrArtifactRepository;
use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\QrArtifact;

final class InMemoryQrArtifactRepository implements QrArtifactRepository
{
    /** @var array<string,QrArtifact> */ public array $artifacts = [];
    public function store(QrArtifact $artifact): void { $this->artifacts[$artifact->id] = $artifact; }
}

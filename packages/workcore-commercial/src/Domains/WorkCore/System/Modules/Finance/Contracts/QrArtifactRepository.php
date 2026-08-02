<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Payments\QrArtifact;

interface QrArtifactRepository
{
    public function store(QrArtifact $artifact): void;
}

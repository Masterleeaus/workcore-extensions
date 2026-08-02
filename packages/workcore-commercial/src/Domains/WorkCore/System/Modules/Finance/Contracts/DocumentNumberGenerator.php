<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Numbering\SequenceKey;
use DateTimeImmutable;

interface DocumentNumberGenerator
{
    public function next(string $companyId, SequenceKey $sequence, DateTimeImmutable $effectiveAt): string;
}

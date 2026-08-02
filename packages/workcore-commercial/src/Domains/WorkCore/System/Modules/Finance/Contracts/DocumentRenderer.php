<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

interface DocumentRenderer
{
    /** @param array<string, mixed> $snapshot */
    public function render(string $documentType, array $snapshot): string;
}

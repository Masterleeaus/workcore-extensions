<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Contracts;

interface RecordSchemaInspectorContract
{
    /** @return array<int,string> */
    public function columns(string $table): array;
}

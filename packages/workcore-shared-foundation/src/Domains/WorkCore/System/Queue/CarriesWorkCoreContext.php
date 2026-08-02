<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Queue;

interface CarriesWorkCoreContext
{
    /** @return array{tenant:array<string,mixed>,operation:array<string,mixed>} */
    public function workCoreContextPayload(): array;
}

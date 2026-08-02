<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

interface ActionInput
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}

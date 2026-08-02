<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\IdentifierGenerator;

final class InMemoryIdentifierGenerator implements IdentifierGenerator
{
    private int $next = 1;
    public function uuid(): string { return sprintf('00000000-0000-4000-8000-%012d', $this->next++); }
}

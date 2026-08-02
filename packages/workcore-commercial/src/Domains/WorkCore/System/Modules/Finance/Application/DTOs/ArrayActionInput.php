<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\DTOs;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;

final readonly class ArrayActionInput implements ActionInput
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values = [])
    {
    }

    public function toArray(): array
    {
        return $this->values;
    }
}

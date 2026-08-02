<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

interface ActionInputFactory
{
    /** @param array<string,mixed> $payload */
    public function make(string $actionKey, array $payload): ActionInput;
}

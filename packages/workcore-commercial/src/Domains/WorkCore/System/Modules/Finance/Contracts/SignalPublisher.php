<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

interface SignalPublisher
{
    /** @param array<string, mixed> $payload */
    public function publish(string $signal, array $payload): void;
}

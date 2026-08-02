<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

interface TitanTalkTransport
{
    /** @param array<string,mixed> $payload */
    public function send(string $eventType, array $payload, string $companyId, string $correlationId): string;
}

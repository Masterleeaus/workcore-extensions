<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\TitanTalkTransport;
use RuntimeException;

final class FailClosedTitanTalkTransport implements TitanTalkTransport
{
    public function send(string $eventType,array $payload,string $companyId,string $correlationId):string{throw new RuntimeException('Titan Talk transport is not bound.');}
}

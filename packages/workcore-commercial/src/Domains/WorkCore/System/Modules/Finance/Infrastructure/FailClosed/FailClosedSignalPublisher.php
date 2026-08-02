<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\FailClosed;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\SignalPublisher;
use RuntimeException;

final class FailClosedSignalPublisher implements SignalPublisher
{
    public function publish(string $signal,array $payload):void{throw new RuntimeException('Titan Signal publisher is not bound.');}
}

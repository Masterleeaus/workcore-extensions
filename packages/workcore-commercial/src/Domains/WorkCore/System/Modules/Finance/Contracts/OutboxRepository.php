<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Domain\Events\OutboxEvent;

interface OutboxRepository
{
    public function append(OutboxEvent $event): void;
}

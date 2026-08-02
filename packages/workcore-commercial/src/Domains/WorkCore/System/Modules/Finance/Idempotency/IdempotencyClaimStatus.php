<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Idempotency;

enum IdempotencyClaimStatus: string
{
    case Acquired = 'acquired';
    case Replay = 'replay';
    case InProgress = 'in_progress';
    case Conflict = 'conflict';
}

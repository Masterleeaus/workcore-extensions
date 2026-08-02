<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\People\Domain\Performance;

enum PerformanceObjectiveState: string
{
    case Draft = 'draft';
    case Active = 'active';
    case AtRisk = 'at_risk';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

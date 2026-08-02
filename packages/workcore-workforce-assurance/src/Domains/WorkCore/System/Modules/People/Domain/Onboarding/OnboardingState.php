<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\People\Domain\Onboarding;

enum OnboardingState: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

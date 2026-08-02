<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\People\Domain\Employment;

enum EmploymentState: string
{
    case Draft = 'draft';
    case PreEmployment = 'pre_employment';
    case Onboarding = 'onboarding';
    case Active = 'active';
    case Suspended = 'suspended';
    case Offboarding = 'offboarding';
    case Ended = 'ended';
}

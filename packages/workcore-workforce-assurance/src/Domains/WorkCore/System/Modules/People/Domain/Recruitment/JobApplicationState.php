<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\People\Domain\Recruitment;

enum JobApplicationState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Screening = 'screening';
    case Interview = 'interview';
    case Offer = 'offer';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}

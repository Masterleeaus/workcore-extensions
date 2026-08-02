<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\Reconciliation;

enum MatchStatus: string
{
    case Proposed = 'proposed';
    case NeedsReview = 'needs_review';
    case Rejected = 'rejected';
}

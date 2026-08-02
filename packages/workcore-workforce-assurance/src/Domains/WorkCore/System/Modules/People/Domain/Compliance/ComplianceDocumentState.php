<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\People\Domain\Compliance;

enum ComplianceDocumentState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Revoked = 'revoked';
}

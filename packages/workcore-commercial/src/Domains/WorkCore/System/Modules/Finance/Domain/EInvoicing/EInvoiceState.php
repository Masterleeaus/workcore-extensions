<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Domain\EInvoicing;

enum EInvoiceState: string
{
    case Draft = 'draft';
    case Validated = 'validated';
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}

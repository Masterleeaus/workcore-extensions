<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Integrations\Runtime;

final readonly class ExternalEventReceipt
{
    public function __construct(public ExternalEventReceiptStatus $status, public string $id) {}
}

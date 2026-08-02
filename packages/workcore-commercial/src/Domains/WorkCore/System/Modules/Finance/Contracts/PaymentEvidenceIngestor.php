<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Contracts;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionResult;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\IngestPaymentEvidenceInput;

interface PaymentEvidenceIngestor
{
    public function ingest(IngestPaymentEvidenceInput $input, ActionContext $context): ActionResult;
}

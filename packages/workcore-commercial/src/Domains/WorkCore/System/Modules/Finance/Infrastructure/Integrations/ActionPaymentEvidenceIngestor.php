<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Integrations;

use App\Domains\WorkCore\System\Modules\Finance\Application\Actions\IngestAndMatchPaymentEvidence;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionResult;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\IngestPaymentEvidenceInput;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\PaymentEvidenceIngestor;

final class ActionPaymentEvidenceIngestor implements PaymentEvidenceIngestor
{
    public function __construct(private readonly IngestAndMatchPaymentEvidence $action) {}
    public function ingest(IngestPaymentEvidenceInput $input,ActionContext $context):ActionResult{return $this->action->execute($input,$context);}
}

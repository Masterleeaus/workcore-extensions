<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\AI\Tools;

use App\Domains\WorkCore\System\Modules\Finance\Application\Actions\IngestAndMatchPaymentEvidence;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\IngestPaymentEvidenceInput;
use DateTimeImmutable;

final readonly class IngestPaymentEvidenceTool
{
    public function __construct(private IngestAndMatchPaymentEvidence $action){}
    public function definition(): array{return ['name'=>'money.ingest_payment_evidence','description'=>'Record device, bank-import or customer payment evidence, rank invoice matches, and safely auto-allocate only high-confidence results.','risk'=>'medium'];}
    public function invoke(array $input,ActionContext $context): array{return $this->action->execute(new IngestPaymentEvidenceInput((string)$input['source_type'],(int)$input['amount_minor'],(string)$input['currency_code'],(string)($input['reference_text']??''),isset($input['payer_hint'])?(string)$input['payer_hint']:null,(string)$input['raw_payload_hash'],new DateTimeImmutable((string)$input['observed_at']),isset($input['source_device_id'])?(string)$input['source_device_id']:null,(string)($input['payment_method']??'bank_transfer')),$context)->toArray();}
}

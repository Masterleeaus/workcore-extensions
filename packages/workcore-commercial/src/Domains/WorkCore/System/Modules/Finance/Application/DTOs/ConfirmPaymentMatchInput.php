<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\DTOs;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;
use InvalidArgumentException;

final readonly class ConfirmPaymentMatchInput implements ActionInput
{
    public function __construct(public string $evidenceId,public string $invoiceId)
    {
        if(trim($evidenceId)===''||trim($invoiceId)==='')throw new InvalidArgumentException('Evidence and invoice IDs are required.');
    }
    public function toArray(): array{return ['evidence_id'=>$this->evidenceId,'invoice_id'=>$this->invoiceId];}
}

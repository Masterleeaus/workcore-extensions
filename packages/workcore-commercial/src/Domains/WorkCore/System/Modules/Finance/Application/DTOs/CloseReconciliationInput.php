<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\DTOs;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;
use InvalidArgumentException;

final readonly class CloseReconciliationInput implements ActionInput
{
    public function __construct(public string $reconciliationId){if(trim($reconciliationId)==='')throw new InvalidArgumentException('Reconciliation ID is required.');}
    public function toArray():array{return ['reconciliation_id'=>$this->reconciliationId];}
}

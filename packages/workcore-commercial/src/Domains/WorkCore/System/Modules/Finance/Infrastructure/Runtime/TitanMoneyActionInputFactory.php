<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\Runtime;

use App\Domains\WorkCore\System\Modules\Finance\Application\Actions\CloseReconciliation;
use App\Domains\WorkCore\System\Modules\Finance\Application\Actions\ExecuteCollectionFollowup;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\CloseReconciliationInput;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ExecuteCollectionFollowupInput;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInputFactory;
use LogicException;

final class TitanMoneyActionInputFactory implements ActionInputFactory
{
    public function make(string $actionKey,array $payload):ActionInput
    {
        return match($actionKey){
            ExecuteCollectionFollowup::ACTION_KEY=>new ExecuteCollectionFollowupInput((string)($payload['invoice_id']??''),(string)($payload['step_code']??''),array_values($payload['channels']??[]),(string)($payload['tone']??'friendly'),(bool)($payload['approval_required']??false)),
            CloseReconciliation::ACTION_KEY=>new CloseReconciliationInput((string)($payload['reconciliation_id']??'')),
            default=>throw new LogicException("No scheduled input factory exists for Titan Money action [$actionKey]."),
        };
    }
}

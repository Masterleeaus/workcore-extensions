<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Integrations\Pulse;

use App\Domains\WorkCore\System\Modules\Finance\Application\Actions\ExecuteCollectionFollowup;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ExecuteCollectionFollowupInput;

final readonly class ExecuteCollectionFollowupListener
{
    public function __construct(private ExecuteCollectionFollowup $action){}
    public function handle(array $payload,ActionContext $context): array{$step=$payload['step']??[];return $this->action->execute(new ExecuteCollectionFollowupInput((string)$payload['invoice_id'],(string)($step['code']??'followup'),array_values($step['channels']??['email']),(string)($step['tone']??'friendly'),(bool)($step['approval_required']??false)),$context)->toArray();}
}

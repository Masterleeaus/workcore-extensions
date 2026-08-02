<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Infrastructure\InMemory;

use App\Domains\WorkCore\System\Modules\Finance\Application\DTOs\ActionContext;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionAttemptRepository;

final class InMemoryActionAttemptRepository implements ActionAttemptRepository
{
    /** @var list<array<string,mixed>> */ public array $attempts=[];
    public function record(string $actionKey,string $state,int $attemptNumber,array $result,ActionContext $context,?string $errorCode=null): void { $this->attempts[]=compact('actionKey','state','attemptNumber','result','context','errorCode'); }
}

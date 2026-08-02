<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Contracts;

use App\Domains\WorkCore\System\Actions\ActionHandlerResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;

interface BusinessActionHandlerContract
{
    public function handle(ActionRequest $request): ActionHandlerResult;
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Contracts;

use App\Domains\WorkCore\System\Actions\ActionDefinition;
use App\Domains\WorkCore\System\Actions\ActionRequest;

interface ConfirmationVerifierContract
{
    public function verify(ActionDefinition $definition, ActionRequest $request): bool;
}

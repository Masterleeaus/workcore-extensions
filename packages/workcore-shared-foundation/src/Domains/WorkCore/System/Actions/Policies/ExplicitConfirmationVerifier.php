<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Policies;

use App\Domains\WorkCore\System\Actions\ActionDefinition;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\ConfirmationVerifierContract;

final class ExplicitConfirmationVerifier implements ConfirmationVerifierContract
{
    public function verify(ActionDefinition $definition, ActionRequest $request): bool
    {
        $required = $definition->requiresConfirmation || in_array($definition->risk, ['high', 'critical'], true);

        return ! $required || ($request->confirmationId !== null && trim($request->confirmationId) !== '');
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Approvals;

use App\Domains\WorkCore\System\Actions\ActionDefinition;
use App\Domains\WorkCore\System\Actions\ActionRequest;
use App\Domains\WorkCore\System\Actions\Contracts\ConfirmationVerifierContract;

final class BoundConfirmationVerifier implements ConfirmationVerifierContract
{
    /** @param list<string> $aiSources */
    public function __construct(
        private ConfirmationGrantSigner $signer,
        private ConfirmationNonceStoreContract $nonces,
        private array $aiSources,
        private bool $enforceAll = false,
        private bool $allowLegacyHumanConfirmation = true,
    ) {}

    public function verify(ActionDefinition $definition, ActionRequest $request): bool
    {
        $required = $definition->requiresConfirmation || in_array($definition->risk, ['high', 'critical'], true);
        if (! $required) {
            return true;
        }
        $token = trim((string) $request->confirmationId);
        if ($token === '') {
            return false;
        }

        $isAiSource = in_array(strtolower(trim($request->source)), $this->aiSources, true);
        if (! $this->enforceAll && ! $isAiSource && $this->allowLegacyHumanConfirmation && ! str_contains($token, '.')) {
            return true;
        }
        if (! $this->signer->verify($token, $request->companyId, $request->actorId, $request->key, $request->payloadHash(), $request->idempotencyKey)) {
            return false;
        }
        $claims = $this->signer->claims($token);
        if ($claims === null) {
            return false;
        }

        return $this->nonces->consume(
            (string) $claims['nonce'],
            $request->companyId,
            $request->actorId,
            $request->key,
            time(),
        );
    }
}

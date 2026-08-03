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
        if (! $this->isRequired($definition)) {
            return true;
        }

        $token = trim((string) $request->confirmationId);
        if ($token === '') {
            return false;
        }
        if ($this->isAllowedLegacyHumanToken($request, $token)) {
            return true;
        }

        return $this->signer->verify(
            $token,
            $request->companyId,
            $request->actorId,
            $request->key,
            $request->payloadHash(),
            $request->idempotencyKey,
        );
    }

    public function consume(ActionDefinition $definition, ActionRequest $request): bool
    {
        if (! $this->isRequired($definition)) {
            return true;
        }

        $token = trim((string) $request->confirmationId);
        if ($token === '') {
            return false;
        }
        if ($this->isAllowedLegacyHumanToken($request, $token)) {
            return true;
        }
        if (! $this->signer->verify(
            $token,
            $request->companyId,
            $request->actorId,
            $request->key,
            $request->payloadHash(),
            $request->idempotencyKey,
        )) {
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

    private function isRequired(ActionDefinition $definition): bool
    {
        return $definition->requiresConfirmation || in_array($definition->risk, ['high', 'critical'], true);
    }

    private function isAllowedLegacyHumanToken(ActionRequest $request, string $token): bool
    {
        $isAiSource = in_array(strtolower(trim($request->source)), $this->aiSources, true);

        return ! $this->enforceAll
            && ! $isAiSource
            && $this->allowLegacyHumanConfirmation
            && ! str_contains($token, '.');
    }
}

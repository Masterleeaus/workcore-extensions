<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Persistence;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

abstract class TenantScopedRepository
{
    public function __construct(
        protected ConnectionInterface $db,
        protected TenantContextContract $tenant,
    ) {}

    final protected function companyId(): int
    {
        $companyId = $this->tenant->companyId();
        if ($companyId === null || $companyId < 1) {
            throw new RuntimeException('A valid WorkCore company context is required.');
        }
        return $companyId;
    }

    final protected function actorId(): int
    {
        $actorId = $this->tenant->userId();
        if ($actorId === null || $actorId < 1) {
            throw new RuntimeException('An authenticated WorkCore actor is required.');
        }
        return $actorId;
    }

    final protected function assertCompany(int $companyId): void
    {
        if ($companyId !== $this->companyId()) {
            throw new RuntimeException('Repository company does not match the active WorkCore company.');
        }
    }
}

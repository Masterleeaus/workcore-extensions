<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

use InvalidArgumentException;

final readonly class KnowledgeAccessScope
{
    /** @param list<string> $allowedVisibilities @param list<string> $allowedPermissions */
    public function __construct(
        public int $companyId,
        public int $actorId,
        public array $allowedVisibilities,
        public array $allowedPermissions = [],
    ) {
        if ($companyId < 1 || $actorId < 1 || $allowedVisibilities === []) {
            throw new InvalidArgumentException('Knowledge access requires company, actor and at least one visibility.');
        }

        foreach ($allowedVisibilities as $visibility) {
            if (! is_string($visibility) || ! preg_match('/^[a-z][a-z0-9_.:-]{1,79}$/', $visibility)) {
                throw new InvalidArgumentException('Knowledge visibility scope is invalid.');
            }
        }

        foreach ($allowedPermissions as $permission) {
            if (! is_string($permission) || ! preg_match('/^[a-z][a-z0-9_.:-]{2,159}$/', $permission)) {
                throw new InvalidArgumentException('Knowledge permission scope is invalid.');
            }
        }
    }

    public function allows(string $visibility, ?int $ownerUserId, ?string $requiredPermission): bool
    {
        if (! in_array($visibility, $this->allowedVisibilities, true)) {
            return false;
        }

        if ($ownerUserId !== null && $ownerUserId !== $this->actorId) {
            return false;
        }

        if ($requiredPermission !== null && ! in_array($requiredPermission, $this->allowedPermissions, true)) {
            return false;
        }

        return true;
    }
}

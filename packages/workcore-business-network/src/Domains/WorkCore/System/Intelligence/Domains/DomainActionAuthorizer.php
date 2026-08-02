<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;

final readonly class DomainActionAuthorizer
{
    public function __construct(private PermissionResolverContract $permissions) {}

    /**
     * @param list<DomainActionDescriptor> $actions
     * @return list<DomainActionDescriptor>
     */
    public function allowed(array $actions, int $actorId, int $companyId): array
    {
        return array_values(array_filter(
            $actions,
            fn (DomainActionDescriptor $action): bool => $this->permissions->allows($actorId, $companyId, $action->permission),
        ));
    }

    /** @param list<DomainActionDescriptor> $actions */
    public function allowedSuggested(array $actions, ?string $actionKey, int $actorId, int $companyId): ?DomainActionDescriptor
    {
        if ($actionKey === null || trim($actionKey) === '') {
            return null;
        }

        foreach ($actions as $action) {
            if ($action->actionKey === $actionKey && $this->permissions->allows($actorId, $companyId, $action->permission)) {
                return $action;
            }
        }

        return null;
    }
}

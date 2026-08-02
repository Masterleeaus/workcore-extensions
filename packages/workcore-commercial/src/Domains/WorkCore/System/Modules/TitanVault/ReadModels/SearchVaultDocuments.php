<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\TitanVault\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;

/**
 * Searches metadata only. Encrypted content, storage paths and secret material
 * are intentionally excluded from the result surface.
 */
final class SearchVaultDocuments
{
    public function __construct(
        private TenantContextContract $tenant,
        private PermissionResolverContract $permissions,
        private ConnectionInterface $db,
    ) {}

    /** @param array<string,mixed> $filters @return array{items:list<array<string,mixed>>,count:int} */
    public function execute(array $filters = [], int $perPage = 25, ?int $companyId = null, ?int $actorId = null): array
    {
        $companyId ??= $this->tenant->companyId();
        $actorId ??= $this->tenant->userId();
        if ($actorId === null || ! $this->permissions->allows($actorId, $companyId, (string) config('workcore.vault.permissions.view'))) {
            throw new AuthorizationException('TitanVault document view permission is required.');
        }

        $perPage = max(1, min(100, $perPage));
        $query = $this->db->table('tz_vault_documents')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');

        $search = trim((string) ($filters['query'] ?? ''));
        if ($search !== '') {
            $query->where(static function ($builder) use ($search): void {
                $builder->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }
        foreach (['status', 'classification', 'owner_type', 'owner_public_id'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $query->where($field, $value);
            }
        }

        $items = $query->orderByDesc('updated_at')->orderByDesc('id')->limit($perPage)
            ->get([
                'public_id', 'title', 'description', 'classification', 'status', 'owner_type',
                'owner_public_id', 'current_version', 'retention_category', 'expires_at',
                'approved_at', 'archived_at', 'created_at', 'updated_at',
            ])->map(static fn (object $row): array => (array) $row)->all();

        return ['items' => $items, 'count' => count($items)];
    }
}

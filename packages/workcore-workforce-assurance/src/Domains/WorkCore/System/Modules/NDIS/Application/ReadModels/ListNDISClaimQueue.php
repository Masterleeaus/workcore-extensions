<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Application\ReadModels;

use App\Domains\WorkCore\System\Contracts\{PermissionResolverContract,TenantContextContract};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;

final class ListNDISClaimQueue
{
    public function __construct(private TenantContextContract $tenant, private ConnectionInterface $db, private PermissionResolverContract $permissions) {}

    /** @return array<int,array<string,mixed>> */
    public function execute(?string $status = null, int $limit = 200, ?int $companyId = null): array
    {
        $companyId ??= $this->tenant->companyId();
        $userId = $this->tenant->userId();
        if ($userId === null || ! $this->permissions->allows($userId, $companyId, (string) config('workcore.ndis.permissions.claims'))) {
            throw new AuthorizationException('NDIS claim-management permission is required.');
        }
        $limit = max(1, min($limit, 500));
        $query = $this->db->table('tz_ndis_claim_lines as c')->join('tz_ndis_participants as p', 'p.id', '=', 'c.participant_id')
            ->join('tz_ndis_support_deliveries as d', 'd.id', '=', 'c.delivery_id')
            ->where('c.company_id', $companyId)->whereNull('c.deleted_at');
        if ($status !== null && trim($status) !== '') $query->where('c.status', $status);
        return $query->orderBy('c.service_date')->orderBy('p.display_name')->limit($limit)
            ->get(['c.public_id','c.claim_reference','c.support_item_code','c.claim_type','c.service_date','c.unit','c.quantity','c.rate_snapshot','c.amount','c.funding_management','c.status','c.hold_reason','p.public_id as participant_public_id','p.display_name as participant_name','d.public_id as delivery_public_id'])
            ->map(static fn (object $row): array => (array) $row)->all();
    }
}

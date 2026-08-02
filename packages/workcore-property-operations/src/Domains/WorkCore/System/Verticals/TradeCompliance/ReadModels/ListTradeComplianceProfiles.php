<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\TradeCompliance\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;

final class ListTradeComplianceProfiles
{
    public function __construct(
        private TenantContextContract $tenant,
        private ConnectionInterface $db,
        private PermissionResolverContract $permissions,
    ) {}

    /** @return array<string,mixed> */
    public function execute(?string $verticalKey = null, ?string $businessLinePublicId = null): array
    {
        $companyId = (int) $this->tenant->companyId();
        $actorId = (int) $this->tenant->userId();
        if (! $this->permissions->allows($actorId, $companyId, (string) config('workcore.trade_compliance.permissions.view'))) {
            throw new AuthorizationException('The actor is not permitted to view trade compliance profiles.');
        }
        $profiles = $this->db->table('tz_trade_compliance_profiles as profile')
            ->leftJoin('tz_business_lines as line', 'line.id', '=', 'profile.business_line_id')
            ->where('profile.company_id', $companyId)
            ->whereNull('profile.deleted_at')
            ->when($verticalKey, fn ($query) => $query->where('profile.vertical_key', $verticalKey))
            ->when($businessLinePublicId, fn ($query) => $query->where('line.public_id', $businessLinePublicId))
            ->orderBy('profile.vertical_key')
            ->orderBy('profile.name')
            ->get([
                'profile.public_id', 'profile.vertical_key', 'profile.service_code', 'profile.jurisdiction',
                'profile.name', 'profile.status', 'profile.effective_from', 'profile.effective_to',
                'profile.requirements', 'profile.default_warranty_days', 'profile.settings',
                'line.public_id as business_line_public_id', 'line.name as business_line_name',
            ])->map(function (object $row): array {
                $record = (array) $row;
                foreach (['requirements', 'settings'] as $field) {
                    $decoded = is_string($record[$field] ?? null) ? json_decode($record[$field], true) : $record[$field] ?? [];
                    $record[$field] = is_array($decoded) ? $decoded : [];
                }
                return $record;
            })->all();
        return ['profiles' => $profiles, 'count' => count($profiles)];
    }
}

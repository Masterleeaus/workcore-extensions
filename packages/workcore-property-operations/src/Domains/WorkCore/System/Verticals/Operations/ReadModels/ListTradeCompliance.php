<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Operations\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;

final class ListTradeCompliance
{
    public function __construct(
        private TenantContextContract $tenant,
        private ConnectionInterface $db,
        private PermissionResolverContract $permissions,
    ) {}

    /** @return array<string,mixed> */
    public function execute(?string $verticalKey = null, ?string $workerPublicId = null): array
    {
        $companyId = (int) $this->tenant->companyId();
        $actorId = (int) $this->tenant->userId();
        if (! $this->permissions->allows(
            $actorId,
            $companyId,
            (string) config('workcore.verticals.permissions.compliance'),
        )) {
            throw new AuthorizationException('The actor is not permitted to view trade compliance records.');
        }

        $licences = $this->db->table('tz_trade_licences as l')
            ->join('tz_workers as w', function ($join): void {
                $join->on('w.id', '=', 'l.worker_id')->on('w.company_id', '=', 'l.company_id');
            })
            ->leftJoin('tz_business_lines as bl', 'bl.id', '=', 'l.business_line_id')
            ->where('l.company_id', $companyId)
            ->whereNull('l.deleted_at')
            ->when($verticalKey, fn ($query) => $query->where('l.vertical_key', $verticalKey))
            ->when($workerPublicId, fn ($query) => $query->where('w.public_id', $workerPublicId))
            ->orderBy('l.expires_on')
            ->limit(500)
            ->get([
                'l.public_id', 'l.vertical_key', 'l.licence_type', 'l.licence_class', 'l.licence_number',
                'l.jurisdiction', 'l.issuer', 'l.status', 'l.issued_on', 'l.expires_on', 'l.verified_at',
                'w.public_id as worker_public_id', 'w.display_name as worker_name',
                'bl.public_id as business_line_public_id', 'bl.name as business_line_name',
            ])->map(static fn (object $record): array => (array) $record)->all();

        $certificates = $this->db->table('tz_work_order_certificates as c')
            ->join('tz_work_orders as wo', function ($join): void {
                $join->on('wo.id', '=', 'c.work_order_id')->on('wo.company_id', '=', 'c.company_id');
            })
            ->leftJoin('tz_workers as w', 'w.id', '=', 'c.worker_id')
            ->where('c.company_id', $companyId)
            ->whereNull('c.deleted_at')
            ->when($verticalKey, fn ($query) => $query->where('c.vertical_key', $verticalKey))
            ->when($workerPublicId, fn ($query) => $query->where('w.public_id', $workerPublicId))
            ->orderByDesc('c.issued_at')
            ->limit(250)
            ->get([
                'c.public_id', 'c.vertical_key', 'c.certificate_type', 'c.certificate_number',
                'c.status', 'c.issued_at', 'c.document_path',
                'wo.public_id as work_order_public_id', 'wo.number as work_order_number',
                'w.public_id as worker_public_id', 'w.display_name as worker_name',
            ])->map(static fn (object $record): array => (array) $record)->all();

        $setting = $this->db->table('tz_company_settings')
            ->where('company_id', $companyId)
            ->where('setting_key', 'operations.compliance_requirements')
            ->value('value');
        $requirements = $this->decodeSetting($setting);

        return [
            'requirements' => $requirements,
            'licences' => $licences,
            'certificates' => $certificates,
            'summary' => [
                'licence_count' => count($licences),
                'expiring_within_60_days' => count(array_filter($licences, static function (array $item): bool {
                    if (empty($item['expires_on'])) {
                        return false;
                    }
                    $days = (strtotime((string) $item['expires_on']) - strtotime(date('Y-m-d'))) / 86400;

                    return $days >= 0 && $days <= 60;
                })),
                'certificate_count' => count($certificates),
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function decodeSetting(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (! is_array($value)) {
            return [];
        }
        $requirements = $value['value'] ?? [];

        return is_array($requirements) ? array_values($requirements) : [];
    }
}

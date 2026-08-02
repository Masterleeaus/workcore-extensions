<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\TradeCompliance;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final class TradeComplianceProfileResolver
{
    public function __construct(
        private ConnectionInterface $db,
        private TradeCompliancePolicy $policy,
    ) {}

    /** @return array<string,mixed> */
    public function resolveForWorkOrder(int $companyId, int $workOrderId): array
    {
        $workOrder = $this->db->table('tz_work_orders as wo')
            ->leftJoin('tz_business_lines as bl', 'bl.id', '=', 'wo.business_line_id')
            ->leftJoin('tz_company_verticals as cv', 'cv.id', '=', 'bl.company_vertical_id')
            ->where('wo.company_id', $companyId)
            ->where('wo.id', $workOrderId)
            ->whereNull('wo.deleted_at')
            ->first([
                'wo.id', 'wo.public_id', 'wo.business_line_id', 'wo.created_at',
                'cv.vertical_key',
            ]);
        if ($workOrder === null) {
            throw new InvalidArgumentException('Work order does not belong to the active company.');
        }

        $verticalKey = trim((string) ($workOrder->vertical_key ?? ''));
        if ($verticalKey === '') {
            $verticalKey = (string) ($this->db->table('tz_company_verticals')
                ->where('company_id', $companyId)
                ->where('is_primary', true)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->value('vertical_key') ?? '');
        }

        $serviceCode = $this->db->table('tz_work_order_services')
            ->where('company_id', $companyId)
            ->where('work_order_id', $workOrderId)
            ->orderBy('sort_order')
            ->value('service_code');
        $effectiveOn = substr((string) ($workOrder->created_at ?? now()->toDateString()), 0, 10);

        $profiles = $this->db->table('tz_trade_compliance_profiles')
            ->where('company_id', $companyId)
            ->where('vertical_key', $verticalKey)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($workOrder): void {
                $query->whereNull('business_line_id');
                if ($workOrder->business_line_id !== null) {
                    $query->orWhere('business_line_id', (int) $workOrder->business_line_id);
                }
            })
            ->where(function ($query) use ($serviceCode): void {
                $query->whereNull('service_code');
                if (is_string($serviceCode) && trim($serviceCode) !== '') {
                    $query->orWhere('service_code', trim($serviceCode));
                }
            })
            ->get()
            ->map(function (object $profile): array {
                $row = (array) $profile;
                $row['requirements'] = $this->decode($row['requirements'] ?? null);
                $row['settings'] = $this->decode($row['settings'] ?? null);
                return $row;
            })->all();

        $selected = $this->policy->selectProfile(
            $profiles,
            $workOrder->business_line_id !== null ? (int) $workOrder->business_line_id : null,
            is_string($serviceCode) ? $serviceCode : null,
            $effectiveOn,
        );
        if ($selected !== null) {
            return [
                'source' => 'company_profile',
                'profile_id' => (int) $selected['id'],
                'profile_public_id' => (string) $selected['public_id'],
                'vertical_key' => $verticalKey,
                'business_line_id' => $workOrder->business_line_id !== null ? (int) $workOrder->business_line_id : null,
                'service_code' => is_string($serviceCode) ? $serviceCode : null,
                'requirements' => $this->policy->normaliseRequirements((array) $selected['requirements']),
                'default_warranty_days' => $selected['default_warranty_days'] !== null ? (int) $selected['default_warranty_days'] : null,
                'settings' => (array) ($selected['settings'] ?? []),
            ];
        }

        $template = (array) config('workcore.trade_compliance.templates.' . $verticalKey, []);
        return [
            'source' => $template === [] ? 'none' : 'vertical_template',
            'profile_id' => null,
            'profile_public_id' => null,
            'vertical_key' => $verticalKey,
            'business_line_id' => $workOrder->business_line_id !== null ? (int) $workOrder->business_line_id : null,
            'service_code' => is_string($serviceCode) ? $serviceCode : null,
            'requirements' => $this->policy->normaliseRequirements((array) ($template['requirements'] ?? [])),
            'default_warranty_days' => isset($template['warranty_days']) ? (int) $template['warranty_days'] : null,
            'settings' => [],
        ];
    }

    /** @return array<string,mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

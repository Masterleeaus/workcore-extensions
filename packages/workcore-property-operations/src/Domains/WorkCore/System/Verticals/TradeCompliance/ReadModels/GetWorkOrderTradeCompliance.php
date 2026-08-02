<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\TradeCompliance\ReadModels;

use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Verticals\TradeCompliance\WorkOrderComplianceGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final class GetWorkOrderTradeCompliance
{
    public function __construct(
        private TenantContextContract $tenant,
        private ConnectionInterface $db,
        private PermissionResolverContract $permissions,
        private WorkOrderComplianceGate $gate,
    ) {}

    /** @return array<string,mixed> */
    public function execute(string $workOrderPublicId): array
    {
        $companyId = (int) $this->tenant->companyId();
        $actorId = (int) $this->tenant->userId();
        if (! $this->permissions->allows($actorId, $companyId, (string) config('workcore.trade_compliance.permissions.view'))) {
            throw new AuthorizationException('The actor is not permitted to view trade compliance records.');
        }
        $workOrder = $this->db->table('tz_work_orders')
            ->where('company_id', $companyId)
            ->where('public_id', $workOrderPublicId)
            ->whereNull('deleted_at')
            ->first();
        if ($workOrder === null) {
            throw new InvalidArgumentException('Work order does not belong to the active company.');
        }
        $workOrderId = (int) $workOrder->id;

        return [
            'work_order' => (array) $workOrder,
            'gate' => $this->gate->status($companyId, $workOrderId),
            'permits' => $this->rows('tz_work_order_permits', $companyId, $workOrderId, ['conditions']),
            'safety_controls' => $this->rows('tz_work_order_safety_controls', $companyId, $workOrderId, ['details']),
            'test_results' => $this->rows('tz_work_order_test_results', $companyId, $workOrderId, ['readings']),
            'evidence' => $this->rows('tz_work_order_evidence', $companyId, $workOrderId, ['metadata']),
            'warranties' => $this->rows('tz_work_order_warranties', $companyId, $workOrderId, ['covered_items']),
            'callbacks' => $this->callbacks($companyId, $workOrderId),
            'certificates' => $this->rows('tz_work_order_certificates', $companyId, $workOrderId, ['test_results', 'declaration']),
            'materials' => $this->db->table('tz_work_order_materials as wom')
                ->join('tz_inventory_items as item', function ($join): void {
                    $join->on('item.id', '=', 'wom.item_id')->on('item.company_id', '=', 'wom.company_id');
                })
                ->where('wom.company_id', $companyId)
                ->where('wom.work_order_id', $workOrderId)
                ->orderBy('item.name')
                ->get([
                    'wom.public_id', 'item.public_id as item_public_id', 'item.sku', 'item.name',
                    'wom.planned_quantity', 'wom.reserved_quantity', 'wom.used_quantity',
                    'wom.wasted_quantity', 'wom.returned_quantity', 'wom.unit_cost', 'wom.status',
                ])->map(static fn (object $row): array => (array) $row)->all(),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $table, int $companyId, int $workOrderId, array $jsonFields = []): array
    {
        $query = $this->db->table($table)
            ->where('company_id', $companyId)
            ->where('work_order_id', $workOrderId);
        if (in_array($table, [
            'tz_work_order_permits', 'tz_work_order_safety_controls', 'tz_work_order_test_results',
            'tz_work_order_evidence', 'tz_work_order_warranties', 'tz_work_order_certificates',
        ], true)) {
            $query->whereNull('deleted_at');
        }
        return $query->orderByDesc('id')->limit(500)->get()->map(function (object $row) use ($jsonFields): array {
            $record = (array) $row;
            foreach ($jsonFields as $field) {
                $record[$field] = $this->decode($record[$field] ?? null);
            }
            return $record;
        })->all();
    }

    /** @return list<array<string,mixed>> */
    private function callbacks(int $companyId, int $workOrderId): array
    {
        return $this->db->table('tz_work_order_callbacks as callback')
            ->join('tz_work_orders as source', 'source.id', '=', 'callback.source_work_order_id')
            ->join('tz_work_orders as corrective', 'corrective.id', '=', 'callback.callback_work_order_id')
            ->where('callback.company_id', $companyId)
            ->where(function ($query) use ($workOrderId): void {
                $query->where('callback.source_work_order_id', $workOrderId)
                    ->orWhere('callback.callback_work_order_id', $workOrderId);
            })
            ->whereNull('callback.deleted_at')
            ->orderByDesc('callback.id')
            ->get([
                'callback.public_id', 'callback.reason_code', 'callback.reason', 'callback.chargeable',
                'callback.status', 'callback.resolved_at', 'source.public_id as source_work_order_public_id',
                'corrective.public_id as callback_work_order_public_id',
            ])->map(static fn (object $row): array => (array) $row)->all();
    }

    private function decode(mixed $value): mixed
    {
        if (is_array($value) || $value === null) {
            return $value ?? [];
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

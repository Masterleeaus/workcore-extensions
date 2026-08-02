<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains\Adapters;

use App\Domains\WorkCore\System\Intelligence\Domains\AbstractDomainIntelligenceAdapter;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainActionDescriptor;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainMetricDefinition;
use App\Domains\WorkCore\System\Intelligence\Domains\DomainSignal;

final class SupplyIntelligenceAdapter extends AbstractDomainIntelligenceAdapter
{
    public function key(): string { return 'supply'; }
    public function name(): string { return 'Inventory, Suppliers and Procurement'; }
    public function capability(): string { return 'workcore.inventory'; }
    public function viewPermission(): string { return 'business.inventory.view'; }

    protected function metricDefinitions(): array
    {
        return [
            new DomainMetricDefinition('supply.active_items', 'tz_inventory_items', 'count_where', 'active', [1]),
            new DomainMetricDefinition('supply.low_stock_items', 'tz_stock_balances', 'count_column_compare', 'quantity_on_hand', [], [
                'compare_column' => 'reorder_point', 'compare_operator' => '<=',
                'filters' => [['column' => 'reorder_point', 'operator' => '>', 'value' => 0]],
            ]),
            new DomainMetricDefinition('supply.open_purchase_orders', 'tm_purchase_orders', 'count_where_not', 'state', ['closed', 'cancelled', 'received']),
            new DomainMetricDefinition('supply.overdue_purchase_orders', 'tm_purchase_orders', 'count_date_before', 'expected_on', ['today'], [
                'filters' => [['column' => 'state', 'operator' => 'not_in', 'value' => ['closed', 'cancelled', 'received']]],
            ]),
            new DomainMetricDefinition('supply.unpaid_supplier_bills', 'tm_supplier_bills', 'count_column_compare', 'paid_minor', [], [
                'compare_column' => 'total_minor', 'compare_operator' => '<',
                'filters' => [['column' => 'state', 'operator' => 'not_in', 'value' => ['draft', 'voided', 'cancelled']]],
            ]),
        ];
    }

    protected function signals(array $metrics): array
    {
        $signals = [];
        $low = $this->value($metrics, 'supply.low_stock_items');
        if ($low > 0) {
            $signals[] = new DomainSignal('supply.low_stock', $low >= 10 ? 'high' : 'medium',
                sprintf('%d stock balances are at or below reorder point.', (int) $low), 0.96,
                $this->evidence('supply.low_stock_items', $low), 'workcore.inventory.set_reorder_rule');
        }
        $overdue = $this->value($metrics, 'supply.overdue_purchase_orders');
        if ($overdue > 0) {
            $signals[] = new DomainSignal('supply.overdue_purchase_orders', $overdue >= 5 ? 'high' : 'medium',
                sprintf('%d purchase orders are past their expected date.', (int) $overdue), 0.94,
                $this->evidence('supply.overdue_purchase_orders', $overdue), 'workcore.money.procurement.purchase_order.update');
        }
        return $signals;
    }

    public function actions(): array
    {
        return [
            new DomainActionDescriptor('workcore.inventory.set_reorder_rule', 'Propose an inventory reorder-rule adjustment.', 'medium', 'business.inventory.manage', true),
            new DomainActionDescriptor('workcore.stock.reserve', 'Propose stock reservation for confirmed work.', 'medium', 'business.inventory.reserve', true),
            new DomainActionDescriptor('workcore.money.procurement.purchase_order.update', 'Propose procurement follow-up or purchase-order adjustment.', 'high', 'money.purchase_orders.manage', true),
        ];
    }
}

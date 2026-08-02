<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Contracts\Records\Adapters;

use App\Domains\WorkCore\System\Contracts\Records\AssetAccessContract;

final class AssetAccessAdapter extends AbstractDatabaseRecordAccess implements AssetAccessContract
{
    protected function table(): string { return 'tz_assets'; }
    protected function type(): string { return 'asset'; }
    protected function columns(): array
    {
        return [
            'public_id', 'category_id', 'premises_id', 'premises_zone_public_id',
            'storage_location_id', 'parent_asset_id', 'custodian_worker_id',
            'current_team_public_id', 'current_vehicle_public_id', 'current_job_id',
            'asset_number', 'name', 'asset_type', 'ownership_type', 'status',
            'condition', 'criticality', 'manufacturer', 'model', 'serial_number',
            'barcode', 'registration_number', 'location_description', 'installed_on',
            'acquired_on', 'purchase_cost', 'currency', 'warranty_starts_on',
            'warranty_expires_on', 'supply_item_public_id',
            'supply_goods_receipt_item_public_id', 'supply_purchase_order_public_id',
            'supplier_public_id', 'last_service_at', 'next_service_due_on',
            'last_inspection_at', 'next_inspection_due_on', 'next_calibration_due_on',
            'last_seen_at', 'retired_at', 'disposed_at', 'written_off_at',
            'specifications', 'metadata', 'notes', 'created_at', 'updated_at',
        ];
    }
}

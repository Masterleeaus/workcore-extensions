<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'category_public_id' => ['nullable','string','size:26'],
            'premises_public_id' => ['nullable','string','size:26'],
            'premises_zone_public_id' => ['nullable','string','size:26'],
            'storage_location_public_id' => ['nullable','string','size:26'],
            'parent_asset_public_id' => ['nullable','string','size:26'],
            'custodian_worker_public_id' => ['nullable','string','size:26'],
            'current_team_public_id' => ['nullable','string','size:26'],
            'current_vehicle_public_id' => ['nullable','string','size:26'],
            'current_job_public_id' => ['nullable','string','size:26'],
            'asset_number' => ['nullable','string','max:100'],
            'name' => ['required','string','max:200'],
            'asset_type' => ['sometimes','in:property_asset,safety_asset,plant,equipment,tool,vehicle,appliance,meter,system,consumable_container,general'],
            'ownership_type' => ['sometimes','in:company_owned,owner_owned,tenant_owned,leased,hired,contractor_owned,managed'],
            'status' => ['sometimes','in:ordered,received,available,maintenance_due,unavailable,damaged'],
            'condition' => ['sometimes','in:new,excellent,good,fair,poor,critical,unknown'],
            'criticality' => ['sometimes','in:low,standard,important,critical,life_safety'],
            'manufacturer' => ['nullable','string','max:150'],
            'model' => ['nullable','string','max:150'],
            'serial_number' => ['nullable','string','max:150'],
            'barcode' => ['nullable','string','max:150'],
            'registration_number' => ['nullable','string','max:100'],
            'location_description' => ['nullable','string','max:255'],
            'installed_on' => ['nullable','date'],
            'acquired_on' => ['nullable','date'],
            'purchase_cost' => ['nullable','numeric','min:0'],
            'currency' => ['nullable','string','size:3'],
            'warranty_starts_on' => ['nullable','date'],
            'warranty_expires_on' => ['nullable','date','after_or_equal:warranty_starts_on'],
            'supply_item_public_id' => ['nullable','string','size:26'],
            'supply_goods_receipt_item_public_id' => ['nullable','string','size:26'],
            'supply_purchase_order_public_id' => ['nullable','string','size:26'],
            'supplier_public_id' => ['nullable','string','size:26'],
            'next_service_due_on' => ['nullable','date'],
            'next_inspection_due_on' => ['nullable','date'],
            'next_calibration_due_on' => ['nullable','date'],
            'last_seen_at' => ['nullable','date'],
            'specifications' => ['nullable','array'],
            'metadata' => ['nullable','array'],
            'notes' => ['nullable','string'],
        ];
    }
}

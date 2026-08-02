<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'supply_item_public_id' => ['nullable','string','size:26'],
            'sku' => ['required','string','max:100'],
            'name' => ['required','string','max:200'],
            'item_type' => ['sometimes','in:material,consumable,part,supply,rental,safety_supply,cleaning_supply,spare_part,general'],
            'unit_of_measure' => ['sometimes','string','max:40'],
            'track_stock' => ['sometimes','boolean'],
            'lot_tracking' => ['sometimes','boolean'],
            'serial_tracking' => ['sometimes','boolean'],
            'active' => ['sometimes','boolean'],
            'default_unit_cost' => ['nullable','numeric','min:0'],
            'default_reorder_point' => ['sometimes','numeric','min:0'],
            'default_reorder_quantity' => ['sometimes','numeric','min:0'],
            'description' => ['nullable','string'],
            'metadata' => ['nullable','array'],
        ];
    }
}

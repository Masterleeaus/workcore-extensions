<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateAssetSupplyHandoffRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'max:191'],
            'inventory_item_public_id' => ['nullable', 'string', 'size:26'],
            'supply_item_public_id' => ['required', 'string', 'size:26'],
            'supply_goods_receipt_item_public_id' => ['nullable', 'string', 'size:26'],
            'supply_purchase_order_public_id' => ['nullable', 'string', 'size:26'],
            'supplier_public_id' => ['nullable', 'string', 'size:26'],
            'category_public_id' => ['nullable', 'string', 'size:26'],
            'asset_number' => ['nullable', 'string', 'max:100'],
            'asset_code' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'serial_number' => ['nullable', 'string', 'max:150'],
            'manufacturer' => ['nullable', 'string', 'max:150'],
            'model' => ['nullable', 'string', 'max:150'],
            'condition' => ['sometimes', 'in:new,excellent,good,fair,poor,critical,unknown'],
            'acquired_on' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'serialised' => ['sometimes', 'boolean'],
            'individually_tracked' => ['sometimes', 'boolean'],
            'long_term_assignment' => ['sometimes', 'boolean'],
            'maintained' => ['sometimes', 'boolean'],
            'calibrated' => ['sometimes', 'boolean'],
            'warrantied' => ['sometimes', 'boolean'],
            'depreciated' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

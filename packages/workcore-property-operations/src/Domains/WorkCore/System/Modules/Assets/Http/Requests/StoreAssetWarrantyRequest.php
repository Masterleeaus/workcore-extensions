<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAssetWarrantyRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'provider_supplier_public_id' => ['nullable', 'string', 'size:26'],
            'provider_name' => ['nullable', 'string', 'max:200'],
            'warranty_number' => ['nullable', 'string', 'max:160'],
            'starts_on' => ['required', 'date'],
            'expires_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'terms' => ['nullable', 'array'],
            'warranty_file_reference' => ['nullable', 'string', 'max:255'],
            'supply_purchase_order_public_id' => ['nullable', 'string', 'size:26'],
            'supply_goods_receipt_item_public_id' => ['nullable', 'string', 'size:26'],
            'status' => ['sometimes', 'in:active,expired,void,claimed'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

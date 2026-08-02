<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Inventory\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreStockLocationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'parent_public_id' => ['nullable','string','size:26'],
            'premises_public_id' => ['nullable','string','size:26'],
            'asset_public_id' => ['nullable','string','size:26'],
            'worker_public_id' => ['nullable','string','size:26'],
            'code' => ['required','string','max:100'],
            'name' => ['required','string','max:200'],
            'location_type' => ['sometimes','in:warehouse,storeroom,property_store,vehicle,mobile_kit,worker_stock,room,vendor,virtual'],
            'active' => ['sometimes','boolean'],
            'allow_negative_stock' => ['sometimes','boolean'],
            'location_description' => ['nullable','string','max:255'],
            'metadata' => ['nullable','array'],
        ];
    }
}

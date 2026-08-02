<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreAssetCategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'parent_public_id' => ['nullable','string','size:26'],
            'name' => ['required','string','max:150'],
            'code' => ['nullable','string','max:100'],
            'asset_scope' => ['sometimes','in:property_asset,safety_asset,plant,equipment,tool,vehicle,appliance,meter,system,general'],
            'description' => ['nullable','string'],
            'requires_serial_number' => ['sometimes','boolean'],
            'requires_service_schedule' => ['sometimes','boolean'],
            'requires_maintenance' => ['sometimes','boolean'],
            'requires_calibration' => ['sometimes','boolean'],
            'settings' => ['nullable','array'],
            'active' => ['sometimes','boolean'],
        ];
    }
}

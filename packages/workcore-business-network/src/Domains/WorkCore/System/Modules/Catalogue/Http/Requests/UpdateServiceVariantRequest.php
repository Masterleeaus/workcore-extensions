<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Catalogue\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateServiceVariantRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'key' => ['sometimes', 'required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'default_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'pricing_model' => ['sometimes', 'required', 'in:fixed,hourly,unit,quote,variable'],
            'base_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'status' => ['sometimes', 'required', 'in:active,inactive,archived'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}

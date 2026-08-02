<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Catalogue\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'business_line_public_id' => ['nullable', 'string', 'size:26'],
            'category_public_id' => ['nullable', 'string', 'size:26'],
            'code' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'default_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'pricing_model' => ['sometimes', 'in:fixed,hourly,unit,quote'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'tax_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}

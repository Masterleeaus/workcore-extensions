<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpsertAccommodationRatePlanRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'property_public_id' => ['nullable', 'string', 'size:26'],
            'business_line_public_id' => ['nullable', 'string', 'size:26'],
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name' => ['required', 'string', 'max:191'],
            'pricing_basis' => ['nullable', 'in:night,week,month,stay,person_night,bed_night'],
            'currency' => ['nullable', 'string', 'size:3'],
            'base_amount' => ['required', 'numeric', 'min:0'],
            'minimum_nights' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'maximum_nights' => ['nullable', 'integer', 'min:1', 'max:3650', 'gte:minimum_nights'],
            'active' => ['nullable', 'boolean'],
            'restrictions' => ['nullable', 'array'],
            'cancellation_policy' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

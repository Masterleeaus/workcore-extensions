<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpsertTradeComplianceProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'profile_public_id' => ['nullable', 'string', 'size:26'],
            'business_line_public_id' => ['nullable', 'string', 'size:26'],
            'vertical_key' => ['required', 'in:plumbing,electrical,cleaning,gardening,handyman,pressure_washing'],
            'service_code' => ['nullable', 'string', 'max:120'],
            'jurisdiction' => ['nullable', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:200'],
            'status' => ['nullable', 'in:draft,active,inactive'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'default_warranty_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'requirements' => ['required', 'array', 'max:100'],
            'requirements.*.code' => ['required', 'string', 'max:120'],
            'requirements.*.type' => ['required', 'in:licence,permit,safety_control,test_result,evidence,certificate'],
            'requirements.*.label' => ['required', 'string', 'max:220'],
            'requirements.*.required' => ['nullable', 'boolean'],
            'requirements.*.settings' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ];
    }
}

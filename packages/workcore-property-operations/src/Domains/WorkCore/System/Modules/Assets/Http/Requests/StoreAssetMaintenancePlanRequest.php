<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAssetMaintenancePlanRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'interval_type' => ['sometimes', 'in:calendar,usage,hybrid'],
            'interval_days' => ['nullable', 'integer', 'min:1'],
            'usage_metric' => ['nullable', 'string', 'max:64'],
            'usage_interval' => ['nullable', 'numeric', 'gt:0'],
            'last_completed_usage' => ['nullable', 'numeric', 'min:0'],
            'initial_due_at' => ['nullable', 'date'],
            'next_due_at' => ['nullable', 'date'],
            'calibration_required' => ['sometimes', 'boolean'],
            'service_provider_supplier_public_id' => ['nullable', 'string', 'size:26'],
            'task_template_public_id' => ['nullable', 'string', 'size:26'],
            'requirements' => ['nullable', 'array'],
            'active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

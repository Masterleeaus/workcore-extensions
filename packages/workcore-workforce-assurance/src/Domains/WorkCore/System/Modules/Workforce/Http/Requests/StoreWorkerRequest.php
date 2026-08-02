<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Workforce\Http\Requests;

use App\Domains\WorkCore\System\Verticals\Codebooks\VerticalValidationRules;
use Illuminate\Foundation\Http\FormRequest;

final class StoreWorkerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(VerticalValidationRules $vertical): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'worker_number' => ['nullable', 'string', 'max:100'],
            'worker_type' => ['sometimes', $vertical->rule('worker.type', [
                'employee', 'contractor', 'volunteer', 'casual', 'agent', 'property_manager', 'maintenance', 'inspector',
            ])],
            'display_name' => ['required', 'string', 'max:200'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'employment_status' => ['sometimes', 'in:active,inactive,on_leave,terminated'],
            'timezone' => ['nullable', 'timezone'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:200'],
            'hourly_cost' => ['nullable', 'numeric', 'min:0'],
            'hourly_charge_rate' => ['nullable', 'numeric', 'min:0'],
            'service_areas' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

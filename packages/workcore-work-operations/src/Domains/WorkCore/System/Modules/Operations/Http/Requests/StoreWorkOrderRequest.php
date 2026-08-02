<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Http\Requests;

use App\Domains\WorkCore\System\Verticals\Codebooks\VerticalValidationRules;
use Illuminate\Foundation\Http\FormRequest;

final class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(VerticalValidationRules $vertical): array
    {
        return [
            'business_line_public_id' => ['nullable', 'string', 'size:26'],
            'customer_public_id' => ['required', 'string', 'size:26'],
            'premises_public_id' => ['nullable', 'string', 'size:26'],
            'number' => ['nullable', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['sometimes', $vertical->rule('work_order.priority', ['low', 'normal', 'high', 'urgent'])],
            'status' => ['sometimes', $vertical->rule(
                'work_order.status',
                ['draft', 'ready'],
                ['draft', 'ready'],
            )],
            'source' => ['sometimes', 'string', 'max:50'],
            'due_at' => ['nullable', 'date'],
            'services' => ['sometimes', 'array', 'max:100'],
            'services.*.service_public_id' => ['required', 'string', 'size:26'],
            'services.*.job_template_public_id' => ['nullable', 'string', 'size:26'],
            'services.*.description' => ['nullable', 'string', 'max:5000'],
            'services.*.quantity' => ['sometimes', 'numeric', 'gt:0'],
            'services.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'services.*.estimated_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'services.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

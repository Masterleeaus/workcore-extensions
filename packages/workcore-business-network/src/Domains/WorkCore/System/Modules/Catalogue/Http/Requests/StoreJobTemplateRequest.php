<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Catalogue\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreJobTemplateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'business_line_public_id' => ['nullable', 'string', 'size:26'],
            'service_public_id' => ['required', 'string', 'size:26'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'tasks' => ['sometimes', 'array', 'max:200'],
            'tasks.*.name' => ['required', 'string', 'max:255'],
            'tasks.*.description' => ['nullable', 'string', 'max:5000'],
            'tasks.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'tasks.*.estimated_minutes' => ['nullable', 'integer', 'min:1'],
            'tasks.*.is_required' => ['sometimes', 'boolean'],
        ];
    }
}

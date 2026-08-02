<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSalesPipelineRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'key' => ['nullable', 'alpha_dash', 'max:80'],
            'is_default' => ['sometimes', 'boolean'],
            'stages' => ['nullable', 'array', 'min:2', 'max:30'],
            'stages.*.name' => ['required_with:stages', 'string', 'max:160'],
            'stages.*.key' => ['nullable', 'alpha_dash', 'max:80'],
            'stages.*.color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/'],
            'stages.*.probability' => ['nullable', 'integer', 'between:0,100'],
            'stages.*.is_closed' => ['sometimes', 'boolean'],
            'stages.*.is_won' => ['sometimes', 'boolean'],
            'stages.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}

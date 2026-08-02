<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssignWorkerBusinessLineRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'worker_public_id' => ['required', 'string', 'size:26'],
            'business_line_public_id' => ['required', 'string', 'size:26'],
            'role' => ['sometimes', 'string', 'min:1', 'max:80'],
            'is_primary' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'settings' => ['sometimes', 'array'],
        ];
    }
}

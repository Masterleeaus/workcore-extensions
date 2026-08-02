<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Dispatch\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReassignDispatchAssignmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'assigned_user_id' => ['required', 'integer', 'min:1'],
            'scheduled_start' => ['nullable', 'date', 'required_with:scheduled_end'],
            'scheduled_end' => ['nullable', 'date', 'required_with:scheduled_start'],
            'timezone' => ['nullable', 'timezone'],
            'reason' => ['required', 'string', 'max:5000'],
            'allow_conflicts' => ['sometimes', 'boolean'],
        ];
    }
}

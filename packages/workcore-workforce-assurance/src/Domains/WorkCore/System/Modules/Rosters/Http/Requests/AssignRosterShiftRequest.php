<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssignRosterShiftRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'worker_public_id' => ['required', 'string', 'size:26'],
            'assignment_role' => ['nullable', 'string', 'max:80'],
            'status' => ['sometimes', 'in:assigned,offered,accepted,declined,completed'],
            'allow_conflicts' => ['sometimes', 'boolean'],
            'conflict_override_reason' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_public_id' => ['nullable', 'string', 'size:26'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at', 'required_without:duration_minutes'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'required_without:ended_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_billable' => ['sometimes', 'boolean'],
        ];
    }
}

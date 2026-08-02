<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'timezone' => ['nullable', 'timezone'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'allow_conflicts' => ['sometimes', 'boolean'],
            'prevent_premises_overlap' => ['sometimes', 'boolean'],
            'assets' => ['sometimes', 'array', 'max:100'],
            'assets.*.asset_public_id' => ['required', 'string', 'size:26'],
            'assets.*.role' => ['sometimes', 'string', 'max:50'],
            'assets.*.is_required' => ['sometimes', 'boolean'],
            'assets.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

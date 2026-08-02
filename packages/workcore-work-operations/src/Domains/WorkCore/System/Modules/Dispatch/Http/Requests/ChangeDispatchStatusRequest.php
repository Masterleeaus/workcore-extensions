<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Dispatch\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ChangeDispatchStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:assigned,dispatched,en_route,arrived,in_progress,completed,cancelled,failed'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'travel_distance_meters' => ['nullable', 'integer', 'min:0'],
            'travel_duration_seconds' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

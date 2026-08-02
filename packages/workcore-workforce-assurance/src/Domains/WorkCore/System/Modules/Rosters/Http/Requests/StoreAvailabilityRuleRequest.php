<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAvailabilityRuleRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'worker_public_id' => ['required', 'string', 'size:26'],
            'availability_type' => ['sometimes', 'in:available,unavailable,preferred,on_call'],
            'recurrence_type' => ['sometimes', 'in:weekly,date_range,one_off'],
            'weekday' => ['nullable', 'integer', 'between:1,7'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'timezone' => ['nullable', 'timezone'],
            'source' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

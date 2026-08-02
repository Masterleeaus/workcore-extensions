<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CheckInAccommodationStayRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'space_public_id' => ['nullable', 'string', 'size:26'],
            'guest_public_id' => ['nullable', 'string', 'size:26'],
            'checked_in_at' => ['nullable', 'date'],
            'override_housekeeping' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'required_if:override_housekeeping,true', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}

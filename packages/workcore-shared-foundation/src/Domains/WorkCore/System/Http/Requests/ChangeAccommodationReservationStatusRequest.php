<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ChangeAccommodationReservationStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:tentative,confirmed,cancelled,no_show,expired'],
            'reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:5000'],
        ];
    }
}

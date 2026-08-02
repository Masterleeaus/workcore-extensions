<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ChangeAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:tentative,scheduled,confirmed,in_progress,completed,cancelled,no_show'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CheckOutAccommodationStayRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'checked_out_at' => ['nullable', 'date'],
            'condition' => ['nullable', 'string', 'max:80'],
            'housekeeping_priority' => ['nullable', 'in:low,normal,high,urgent'],
            'housekeeping_due_at' => ['nullable', 'date'],
            'housekeeping_notes' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}

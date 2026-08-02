<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyServicePlanRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:190'],
            'service_type' => ['nullable','string','max:120'],
            'rrule' => ['nullable','string','max:255'],
            'starts_on' => ['nullable','date'],
            'ends_on' => ['nullable','date'],
            'preferred_days' => ['nullable','array'],
            'preferred_times' => ['nullable','array'],
            'notes' => ['nullable','string'],
            'is_active' => ['nullable','boolean'],
        ];
    }
}

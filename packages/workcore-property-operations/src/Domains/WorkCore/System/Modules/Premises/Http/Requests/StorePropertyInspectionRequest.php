<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyInspectionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'inspection_type' => ['nullable','string','max:120'],
            'scheduled_for' => ['nullable','date'],
            'inspected_by' => ['nullable','integer'],
            'status' => ['nullable','string','max:50'],
            'score' => ['nullable','integer','min:0','max:100'],
            'findings' => ['nullable','array'],
            'actions' => ['nullable','array'],
            'completed_at' => ['nullable','date'],
        ];
    }
}

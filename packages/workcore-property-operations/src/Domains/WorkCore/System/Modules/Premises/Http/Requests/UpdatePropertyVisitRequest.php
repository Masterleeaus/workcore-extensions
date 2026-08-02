<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyVisitRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'service_plan_id' => ['nullable','integer'],
            'visit_type' => ['nullable','string','max:120'],
            'scheduled_for' => ['nullable','date'],
            'assigned_to' => ['nullable','integer'],
            'status' => ['nullable','string','max:50'],
            'notes' => ['nullable','string'],
            'completed_at' => ['nullable','date'],
        ];
    }
}

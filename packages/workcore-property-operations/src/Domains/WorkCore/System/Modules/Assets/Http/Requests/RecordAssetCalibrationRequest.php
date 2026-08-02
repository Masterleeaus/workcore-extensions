<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecordAssetCalibrationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'maintenance_plan_public_id' => ['nullable', 'string', 'size:26'],
            'worker_public_id' => ['nullable', 'string', 'size:26'],
            'due_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'result' => ['required', 'in:passed,failed,conditional'],
            'standard' => ['nullable', 'string', 'max:160'],
            'tolerance' => ['nullable', 'string', 'max:160'],
            'measurements' => ['nullable', 'array'],
            'certificate_file_reference' => ['nullable', 'string', 'max:255'],
            'evidence_file_references' => ['nullable', 'array'],
            'service_provider_supplier_public_id' => ['nullable', 'string', 'size:26'],
            'failure_reason' => ['nullable', 'string'],
            'return_to_service' => ['sometimes', 'boolean'],
            'return_to_service_approved_by_user_id' => ['nullable', 'integer', 'min:1'],
            'next_due_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string'],
        ];
    }
}

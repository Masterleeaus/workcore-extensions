<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CompleteAssetMaintenanceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'return_to_service_approved_by_user_id' => ['required', 'integer', 'min:1'],
            'completed_at' => ['nullable', 'date'],
            'downtime_ended_at' => ['nullable', 'date'],
            'findings' => ['nullable', 'string'],
            'root_cause' => ['nullable', 'string'],
            'actions_taken' => ['nullable', 'string'],
            'parts_references' => ['nullable', 'array'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'next_due_on' => ['nullable', 'date'],
            'completed_usage' => ['nullable', 'numeric', 'min:0'],
            'evidence_file_references' => ['nullable', 'array'],
            'reason' => ['nullable', 'string'],
        ];
    }
}

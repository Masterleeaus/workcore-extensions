<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CheckinAssetRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'returned_at' => ['nullable', 'date'],
            'target_status' => ['sometimes', 'in:available,assigned,unavailable,damaged,maintenance_due'],
            'condition_in' => ['nullable', 'array'],
            'condition_in.grade' => ['required_with:condition_in', 'in:new,excellent,good,fair,poor,critical,unknown'],
            'condition_in.summary' => ['nullable', 'string'],
            'condition_in.measurements' => ['nullable', 'array'],
            'condition_in.evidence_file_references' => ['nullable', 'array'],
            'reason' => ['nullable', 'string'],
        ];
    }
}

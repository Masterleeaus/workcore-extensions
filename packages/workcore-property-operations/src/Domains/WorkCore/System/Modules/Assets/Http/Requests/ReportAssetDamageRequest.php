<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReportAssetDamageRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'work_order_public_id' => ['nullable', 'string', 'size:26'],
            'severity' => ['required', 'in:minor,moderate,major,critical'],
            'status' => ['sometimes', 'in:open,assessing,repairing,resolved,written_off'],
            'classification' => ['nullable', 'string', 'max:120'],
            'repair_recommended' => ['sometimes', 'boolean'],
            'description' => ['required', 'string'],
            'condition' => ['nullable', 'array'],
            'condition.grade' => ['required_with:condition', 'in:new,excellent,good,fair,poor,critical,unknown'],
            'condition.summary' => ['nullable', 'string'],
            'evidence_file_references' => ['nullable', 'array'],
            'ai_suggestion' => ['nullable', 'array'],
            'reported_at' => ['nullable', 'date'],
        ];
    }
}

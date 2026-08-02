<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecordAssetConditionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'context' => ['sometimes', 'in:inspection,checkout,checkin,transfer_in,transfer_out,damage,maintenance,calibration'],
            'grade' => ['required', 'in:new,excellent,good,fair,poor,critical,unknown'],
            'summary' => ['nullable', 'string'],
            'measurements' => ['nullable', 'array'],
            'evidence_file_references' => ['nullable', 'array'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }
}

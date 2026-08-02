<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class OverrideAssetStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:ordered,received,available,assigned,checked_out,in_use,maintenance_due,under_maintenance,unavailable,damaged,lost,retired,disposed,written_off'],
            'condition' => ['nullable', 'in:new,excellent,good,fair,poor,critical,unknown'],
            'reason' => ['required', 'string'],
            'approved_by_user_id' => ['required', 'integer', 'min:1'],
            'correlation_id' => ['nullable', 'string', 'max:120'],
            'evidence_file_references' => ['required', 'array', 'min:1'],
        ];
    }
}

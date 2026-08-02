<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Http\Requests;

use App\Domains\WorkCore\System\Verticals\Codebooks\VerticalValidationRules;
use Illuminate\Foundation\Http\FormRequest;

final class StoreRosterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(VerticalValidationRules $vertical): array
    {
        return [
            'premises_public_id' => ['nullable', 'string', 'size:26'],
            'name' => ['required', 'string', 'max:200'],
            'roster_type' => ['sometimes', $vertical->rule('roster.type', [
                'operations', 'office', 'property_management', 'inspection', 'maintenance', 'cleaning',
                'contractor', 'compliance', 'on_call', 'emergency', 'leasing', 'open_home',
                'rooming_house', 'storage_facility',
            ])],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'timezone' => ['nullable', 'timezone'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

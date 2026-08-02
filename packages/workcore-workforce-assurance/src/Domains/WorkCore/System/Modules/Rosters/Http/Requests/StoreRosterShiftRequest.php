<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Http\Requests;

use App\Domains\WorkCore\System\Verticals\Codebooks\VerticalValidationRules;
use Illuminate\Foundation\Http\FormRequest;

final class StoreRosterShiftRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(VerticalValidationRules $vertical): array
    {
        return [
            'roster_public_id' => ['required', 'string', 'size:26'],
            'worker_public_id' => ['nullable', 'string', 'size:26'],
            'premises_public_id' => ['nullable', 'string', 'size:26'],
            'work_order_public_id' => ['nullable', 'string', 'size:26'],
            'appointment_public_id' => ['nullable', 'string', 'size:26'],
            'title' => ['required', 'string', 'max:200'],
            'shift_type' => ['sometimes', $vertical->rule('roster.shift_type', [
                'operations', 'office', 'property_management', 'inspection', 'maintenance', 'cleaning',
                'contractor', 'compliance', 'on_call', 'emergency', 'leasing', 'open_home',
                'rooming_house', 'storage_facility',
            ])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'timezone'],
            'required_worker_count' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'required_skill_public_ids' => ['nullable', 'array'],
            'required_skill_public_ids.*' => ['string', 'size:26'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'allow_conflicts' => ['sometimes', 'boolean'],
            'conflict_override_reason' => ['nullable', 'string', 'max:5000'],
            'assignment_role' => ['nullable', 'string', 'max:80'],
        ];
    }
}

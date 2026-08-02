<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Dispatch\Http\Requests;

use App\Domains\WorkCore\System\Verticals\Codebooks\VerticalValidationRules;
use Illuminate\Foundation\Http\FormRequest;

final class StoreDispatchAssignmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(VerticalValidationRules $vertical): array
    {
        return [
            'dispatch_number' => ['nullable', 'string', 'max:100'],
            'work_order_public_id' => ['nullable', 'string', 'size:26', 'required_without:appointment_public_id'],
            'appointment_public_id' => ['nullable', 'string', 'size:26', 'required_without:work_order_public_id'],
            'assigned_user_id' => ['required', 'integer', 'min:1'],
            'assignment_type' => ['sometimes', $vertical->rule('dispatch.assignment_type', [
                'job', 'maintenance', 'inspection', 'viewing', 'room_turnover', 'move_in', 'move_out',
                'compliance', 'contractor_callout', 'delivery', 'other',
            ])],
            'priority' => ['sometimes', 'in:low,normal,high,urgent,emergency'],
            'scheduled_start' => ['nullable', 'date', 'required_with:scheduled_end'],
            'scheduled_end' => ['nullable', 'date', 'required_with:scheduled_start'],
            'timezone' => ['nullable', 'timezone'],
            'route_group' => ['nullable', 'string', 'max:100'],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'access_notes' => ['nullable', 'string', 'max:10000'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'source' => ['sometimes', 'string', 'max:50'],
            'allow_conflicts' => ['sometimes', 'boolean'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Scheduling\Http\Requests;

use App\Domains\WorkCore\System\Verticals\Codebooks\VerticalValidationRules;
use Illuminate\Foundation\Http\FormRequest;

final class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(VerticalValidationRules $vertical): array
    {
        return [
            'work_order_public_id' => ['nullable', 'string', 'size:26'],
            'customer_public_id' => ['nullable', 'string', 'size:26'],
            'premises_public_id' => ['nullable', 'string', 'size:26'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'appointment_type' => ['sometimes', $vertical->rule('appointment.type', [
                'job', 'inspection', 'viewing', 'maintenance', 'meeting', 'move_in', 'move_out', 'compliance', 'other',
            ])],
            'status' => ['sometimes', 'in:tentative,scheduled,confirmed'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'timezone' => ['nullable', 'timezone'],
            'all_day' => ['sometimes', 'boolean'],
            'access_notes' => ['nullable', 'string', 'max:10000'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'source' => ['sometimes', 'string', 'max:50'],
            'allow_conflicts' => ['sometimes', 'boolean'],
            'prevent_premises_overlap' => ['sometimes', 'boolean'],
            'participants' => ['sometimes', 'array', 'max:100'],
            'participants.*.user_id' => ['nullable', 'integer', 'min:1'],
            'participants.*.contact_public_id' => ['nullable', 'string', 'size:26'],
            'participants.*.display_name' => ['nullable', 'string', 'max:255'],
            'participants.*.email' => ['nullable', 'email', 'max:255'],
            'participants.*.phone' => ['nullable', 'string', 'max:100'],
            'participants.*.role' => ['sometimes', 'in:assignee,attendee,resident,tenant,owner,agent,contractor,customer,observer'],
            'participants.*.response_status' => ['sometimes', 'in:needs_action,accepted,tentative,declined'],
            'participants.*.is_required' => ['sometimes', 'boolean'],
            'assets' => ['sometimes', 'array', 'max:100'],
            'assets.*.asset_public_id' => ['required', 'string', 'size:26'],
            'assets.*.role' => ['sometimes', 'string', 'max:50'],
            'assets.*.is_required' => ['sometimes', 'boolean'],
            'assets.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

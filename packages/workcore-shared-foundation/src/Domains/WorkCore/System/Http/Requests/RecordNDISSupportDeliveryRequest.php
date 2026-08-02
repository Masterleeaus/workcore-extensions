<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecordNDISSupportDeliveryRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'agreement_public_id' => ['required','string','size:26'], 'agreement_item_public_id' => ['required','string','size:26'],
            'goal_public_id' => ['nullable','string','size:26'], 'worker_public_id' => ['nullable','string','size:26'],
            'appointment_public_id' => ['nullable','string','size:26'], 'work_order_public_id' => ['nullable','string','size:26'],
            'premise_public_id' => ['nullable','string','size:26'], 'occupancy_public_id' => ['nullable','string','size:26'],
            'service_date' => ['nullable','date'], 'started_at' => ['nullable','date'], 'ended_at' => ['nullable','date','after_or_equal:started_at'],
            'scheduled_minutes' => ['nullable','integer','min:0'], 'delivered_minutes' => ['nullable','integer','min:0'],
            'outcome' => ['nullable','in:delivered,participant_no_show,late_cancellation,provider_cancelled,not_delivered'],
            'unit' => ['nullable','in:hour,minute,each,day,week,month,kilometre'], 'quantity' => ['nullable','numeric','min:0'],
            'cancellation_claimable_minutes' => ['nullable','integer','min:0'], 'travel_minutes' => ['nullable','integer','min:0'],
            'travel_kilometres' => ['nullable','numeric','min:0'], 'travel_amount' => ['nullable','numeric','min:0'],
            'status' => ['nullable','in:recorded,approved,voided'], 'approved' => ['nullable','boolean'],
            'cancellation_reason' => ['nullable','string','max:5000'], 'delivery_notes' => ['nullable','string','max:10000'],
            'evidence' => ['nullable','array'], 'metadata' => ['nullable','array'],
        ];
    }
}

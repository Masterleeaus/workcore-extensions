<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpsertNDISServiceAgreementRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'plan_public_id' => ['nullable','string','size:26'], 'agreement_number' => ['required','string','max:100'],
            'status' => ['nullable','in:draft,offered,signed,active,suspended,declined,ended,cancelled'],
            'starts_on' => ['required','date'], 'ends_on' => ['nullable','date','after_or_equal:starts_on'], 'signed_at' => ['nullable','date'],
            'funding_management' => ['nullable','in:agency_managed,plan_managed,self_managed,mixed,unknown'], 'currency' => ['nullable','string','size:3'],
            'cancellation_terms' => ['nullable','array'], 'travel_terms' => ['nullable','array'], 'notes' => ['nullable','string','max:10000'], 'metadata' => ['nullable','array'],
            'items' => ['nullable','array','max:500'], 'items.*.plan_budget_public_id' => ['nullable','string','size:26'],
            'items.*.category_code' => ['required_with:items','string','max:80'], 'items.*.support_item_code' => ['required_with:items','string','max:100'],
            'items.*.support_item_name' => ['required_with:items','string','max:191'], 'items.*.unit' => ['nullable','in:hour,minute,each,day,week,month,kilometre'],
            'items.*.unit_rate' => ['required_with:items','numeric','min:0'], 'items.*.maximum_units' => ['nullable','numeric','min:0'],
            'items.*.maximum_amount' => ['nullable','numeric','min:0'], 'items.*.claim_type' => ['nullable','in:standard,travel,cancellation,non_face_to_face,other'],
            'items.*.gst_code' => ['nullable','string','max:20'], 'items.*.active_from' => ['nullable','date'], 'items.*.active_until' => ['nullable','date'],
            'items.*.status' => ['nullable','in:active,inactive,expired'], 'items.*.metadata' => ['nullable','array'],
        ];
    }
}

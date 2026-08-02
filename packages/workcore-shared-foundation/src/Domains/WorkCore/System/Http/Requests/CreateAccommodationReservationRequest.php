<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateAccommodationReservationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'property_public_id' => ['required', 'string', 'size:26'],
            'space_public_ids' => ['required', 'array', 'min:1', 'max:50'],
            'space_public_ids.*' => ['required', 'string', 'size:26', 'distinct'],
            'business_line_public_id' => ['nullable', 'string', 'size:26'],
            'rate_plan_public_id' => ['nullable', 'string', 'size:26'],
            'agreement_public_id' => ['nullable', 'string', 'size:26'],
            'reservation_type' => ['required', 'in:short_stay,hotel,student,rooming_house,ndis_sta,ndis_mta,internal,other'],
            'status' => ['nullable', 'in:draft,tentative,confirmed'],
            'source_channel' => ['nullable', 'string', 'max:60'],
            'external_reference' => ['nullable', 'string', 'max:191'],
            'external_url' => ['nullable', 'url', 'max:500'],
            'primary_guest_name' => ['required', 'string', 'max:191'],
            'primary_guest_email' => ['nullable', 'email:rfc', 'max:255'],
            'primary_guest_phone' => ['nullable', 'string', 'max:80'],
            'arrival_at' => ['required', 'date'],
            'departure_at' => ['required', 'date', 'after:arrival_at'],
            'booked_at' => ['nullable', 'date'],
            'adults' => ['nullable', 'integer', 'min:0', 'max:100'],
            'children' => ['nullable', 'integer', 'min:0', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'currency' => ['nullable', 'string', 'size:3'],
            'subtotal_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'space_amounts' => ['nullable', 'array'],
            'space_amounts.*' => ['numeric', 'min:0'],
            'guests' => ['nullable', 'array', 'max:200'],
            'guests.*.guest_type' => ['nullable', 'in:guest,student,resident,participant,support_person,child,staff,other'],
            'guests.*.display_name' => ['required_with:guests', 'string', 'max:191'],
            'guests.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'guests.*.phone' => ['nullable', 'string', 'max:80'],
            'guests.*.date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'guests.*.is_primary' => ['nullable', 'boolean'],
            'guests.*.accessibility_requirements' => ['nullable', 'string', 'max:5000'],
            'guests.*.emergency_contact' => ['nullable', 'array'],
            'guests.*.metadata' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpsertNDISParticipantContactRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'contact_type' => ['required','in:nominee,guardian,plan_manager,support_coordinator,emergency_contact,advocate,family,other'],
            'customer_contact_public_id' => ['nullable','string','size:26'], 'display_name' => ['required','string','max:191'],
            'organisation_name' => ['nullable','string','max:191'], 'relationship' => ['nullable','string','max:100'],
            'email' => ['nullable','email:rfc','max:255'], 'phone' => ['nullable','string','max:80'], 'authority_scope' => ['nullable','array'],
            'is_primary' => ['nullable','boolean'], 'may_receive_information' => ['nullable','boolean'], 'may_make_decisions' => ['nullable','boolean'],
            'valid_from' => ['nullable','date'], 'valid_until' => ['nullable','date','after_or_equal:valid_from'],
            'consent_status' => ['nullable','in:pending,granted,limited,withdrawn,expired'], 'metadata' => ['nullable','array'],
        ];
    }
}

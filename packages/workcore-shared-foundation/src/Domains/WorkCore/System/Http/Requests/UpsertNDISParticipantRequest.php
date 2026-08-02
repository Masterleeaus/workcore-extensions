<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpsertNDISParticipantRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'customer_public_id' => ['nullable','string','size:26'], 'premise_party_role_public_id' => ['nullable','string','size:26'],
            'occupancy_public_id' => ['nullable','string','size:26'], 'portal_user_id' => ['nullable','integer','min:1'],
            'participant_reference' => ['nullable','string','max:100'], 'ndis_number' => ['nullable','string','max:32'],
            'first_name' => ['required','string','max:100'], 'last_name' => ['required','string','max:100'],
            'preferred_name' => ['nullable','string','max:100'], 'display_name' => ['nullable','string','max:191'],
            'date_of_birth' => ['nullable','date','before_or_equal:today'], 'pronouns' => ['nullable','string','max:80'],
            'email' => ['nullable','email:rfc','max:255'], 'phone' => ['nullable','string','max:80'],
            'preferred_communication' => ['nullable','in:phone,email,sms,portal,letter,interpreter,other'],
            'preferred_language' => ['nullable','string','max:80'], 'interpreter_required' => ['nullable','boolean'],
            'status' => ['nullable','in:prospective,active,suspended,declined,inactive,deceased'],
            'funding_management' => ['nullable','in:agency_managed,plan_managed,self_managed,mixed,unknown'],
            'consent_status' => ['nullable','in:pending,granted,limited,withdrawn,expired'], 'consent_review_on' => ['nullable','date'],
            'risk_level' => ['nullable','in:standard,elevated,high,critical'], 'restricted_access' => ['nullable','boolean'], 'metadata' => ['nullable','array'],
        ];
    }
}

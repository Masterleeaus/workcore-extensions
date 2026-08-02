<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LinkNDISParticipantIncidentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'incident_public_id' => ['required','string','size:26'], 'delivery_public_id' => ['nullable','string','size:26'],
            'relationship' => ['nullable','in:affected_participant,witness,reporter,subject,other'],
            'participant_impact' => ['nullable','in:none,minor,moderate,serious,critical'], 'restrictive_practice' => ['nullable','boolean'],
            'reportable_to_ndis' => ['nullable','boolean'], 'reported_to_ndis_at' => ['nullable','date'],
            'follow_up' => ['nullable','string','max:10000'], 'metadata' => ['nullable','array'],
        ];
    }
}

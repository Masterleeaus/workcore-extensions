<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AddNDISCaseNoteRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'delivery_public_id' => ['nullable','string','size:26'], 'goal_public_id' => ['nullable','string','size:26'],
            'worker_public_id' => ['nullable','string','size:26'], 'note_type' => ['nullable','in:progress,clinical,contact,goal_review,incident_follow_up,medication,behaviour,other'],
            'visibility' => ['nullable','in:team,management,participant,restricted'], 'sensitivity' => ['nullable','in:normal,sensitive,highly_sensitive'],
            'occurred_at' => ['nullable','date'], 'summary' => ['nullable','string','max:500'], 'body' => ['required','string','max:50000'],
            'shared_with_participant' => ['nullable','boolean'], 'metadata' => ['nullable','array'],
        ];
    }
}

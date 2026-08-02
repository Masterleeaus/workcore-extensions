<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpsertNDISGoalRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'plan_public_id' => ['nullable','string','size:26'], 'status' => ['nullable','in:active,paused,achieved,closed'],
            'title' => ['required','string','max:191'], 'description' => ['nullable','string','max:10000'],
            'target_on' => ['nullable','date'], 'achieved_on' => ['nullable','date'], 'outcome' => ['nullable','string','max:10000'],
            'visibility' => ['nullable','in:team,management,participant,restricted'], 'sort_order' => ['nullable','integer','min:0'], 'metadata' => ['nullable','array'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MatchNDISWorkerRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'worker_public_id' => ['required','string','size:26'], 'support_class' => ['nullable','string','max:80'],
            'status' => ['nullable','in:proposed,approved,declined,inactive'], 'compatibility_score' => ['nullable','integer','between:0,100'],
            'matched_preferences' => ['nullable','array'], 'restrictions' => ['nullable','array'], 'reason' => ['nullable','string','max:10000'],
            'starts_on' => ['nullable','date'], 'ends_on' => ['nullable','date','after_or_equal:starts_on'], 'metadata' => ['nullable','array'],
        ];
    }
}

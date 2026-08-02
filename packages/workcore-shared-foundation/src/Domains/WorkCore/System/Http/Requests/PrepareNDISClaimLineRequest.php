<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PrepareNDISClaimLineRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'delivery_public_id' => ['required','string','size:26'], 'claim_reference' => ['nullable','string','max:100'],
            'claim_type' => ['nullable','in:standard,travel,cancellation,non_face_to_face,other'],
            'status' => ['nullable','in:prepared,held'], 'hold_reason' => ['nullable','string','max:10000'],
            'finance_provider' => ['nullable','string','max:80'], 'finance_external_id' => ['nullable','string','max:191'], 'metadata' => ['nullable','array'],
        ];
    }
}

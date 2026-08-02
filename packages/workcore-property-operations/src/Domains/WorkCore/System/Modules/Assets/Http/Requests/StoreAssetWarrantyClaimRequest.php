<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAssetWarrantyClaimRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'claim_reference' => ['nullable', 'string', 'max:160'],
            'state' => ['sometimes', 'in:draft,submitted'],
            'description' => ['required', 'string'],
            'claimed_at' => ['nullable', 'date'],
            'work_order_public_id' => ['nullable', 'string', 'size:26'],
            'evidence_file_references' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

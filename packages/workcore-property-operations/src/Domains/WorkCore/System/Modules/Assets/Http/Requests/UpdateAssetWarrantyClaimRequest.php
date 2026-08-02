<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAssetWarrantyClaimRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'state' => ['required', 'in:draft,submitted,assessing,approved,rejected,repairing,replaced,resolved'],
            'outcome' => ['nullable', 'in:repair,replacement,refund,rejected,other'],
            'replacement_asset_public_id' => ['nullable', 'string', 'size:26'],
            'resolved_at' => ['nullable', 'date'],
            'resolution_notes' => ['nullable', 'string', 'max:10000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

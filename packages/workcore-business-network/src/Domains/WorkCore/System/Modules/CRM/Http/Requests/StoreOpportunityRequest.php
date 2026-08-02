<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreOpportunityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'pipeline_public_id' => ['required', 'string', 'size:26'],
            'stage_public_id' => ['required', 'string', 'size:26'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999.99'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'],
            'lead_public_id' => ['nullable', 'string', 'size:26'],
            'customer_public_id' => ['nullable', 'string', 'size:26'],
            'contact_public_id' => ['nullable', 'string', 'size:26'],
            'owner_user_id' => ['nullable', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

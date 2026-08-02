<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateOpportunityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'amount' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999.99'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'probability' => ['sometimes', 'integer', 'between:0,100'],
            'expected_close_date' => ['sometimes', 'nullable', 'date'],
            'lead_public_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'customer_public_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'contact_public_id' => ['sometimes', 'nullable', 'string', 'size:26'],
            'owner_user_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}

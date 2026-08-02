<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpsertTradeLicenceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'worker_public_id' => ['required', 'string', 'size:26'],
            'business_line_public_id' => ['nullable', 'string', 'size:26'],
            'vertical_key' => ['required', 'in:plumbing,electrical'],
            'licence_type' => ['required', 'string', 'max:100'],
            'licence_class' => ['nullable', 'string', 'max:120'],
            'licence_number' => ['required', 'string', 'max:160'],
            'jurisdiction' => ['required', 'string', 'max:80'],
            'issuer' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:valid,pending_verification,suspended,expired,cancelled'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'verified' => ['nullable', 'boolean'],
            'conditions' => ['nullable', 'array'],
            'document_path' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}

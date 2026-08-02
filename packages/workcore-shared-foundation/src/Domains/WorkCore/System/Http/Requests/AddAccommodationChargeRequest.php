<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AddAccommodationChargeRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'stay_public_id' => ['nullable', 'string', 'size:26'],
            'charge_type' => ['required', 'in:accommodation,cleaning,service,damage,deposit,tax,discount,refund,other'],
            'description' => ['required', 'string', 'max:191'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'unit_amount' => ['required', 'numeric'],
            'tax_amount' => ['nullable', 'numeric'],
            'total_amount' => ['nullable', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'in:pending,posted'],
            'occurred_at' => ['nullable', 'date'],
            'finance_provider' => ['nullable', 'string', 'max:80'],
            'finance_external_id' => ['nullable', 'string', 'max:191'],
            'finance_reference' => ['nullable', 'string', 'max:191'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

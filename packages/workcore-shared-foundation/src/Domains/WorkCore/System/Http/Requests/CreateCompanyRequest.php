<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateCompanyRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash', 'max:150', Rule::unique('tz_companies', 'slug')],
            'timezone' => ['nullable', 'timezone'], 'currency_code' => ['nullable', 'size:3'],
            'country_code' => ['nullable', 'size:2'], 'abn' => ['nullable', 'string', 'max:20'],
            'gst_registered' => ['nullable', 'boolean'],
        ];
    }
}

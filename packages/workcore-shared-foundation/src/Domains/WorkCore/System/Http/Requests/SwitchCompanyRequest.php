<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SwitchCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['company_id' => ['required', 'integer', 'min:1']];
    }
}

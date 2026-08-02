<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PreviewVerticalSetupRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'primary_vertical' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'add_ons' => ['sometimes', 'array', 'max:30'],
            'add_ons.*' => ['required', 'string', 'distinct', 'regex:/^[a-z][a-z0-9_]*$/'],
            'answers' => ['sometimes', 'array'],
        ];
    }
}

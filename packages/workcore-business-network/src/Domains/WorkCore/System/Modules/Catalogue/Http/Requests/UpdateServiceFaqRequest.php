<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Catalogue\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateServiceFaqRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'question' => ['sometimes', 'required', 'string', 'max:1000'],
            'answer' => ['sometimes', 'required', 'string', 'max:100000'],
            'status' => ['sometimes', 'required', 'in:active,inactive,archived'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}

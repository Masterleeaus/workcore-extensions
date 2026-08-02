<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Catalogue\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreServiceCategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'business_line_public_id' => ['nullable', 'string', 'size:26'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

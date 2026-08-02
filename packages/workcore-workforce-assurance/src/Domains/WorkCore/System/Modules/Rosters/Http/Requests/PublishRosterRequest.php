<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PublishRosterRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:published,closed,cancelled,draft'],
            'allow_gaps' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateWorkOrderTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', 'in:pending,in_progress,completed,skipped']];
    }
}

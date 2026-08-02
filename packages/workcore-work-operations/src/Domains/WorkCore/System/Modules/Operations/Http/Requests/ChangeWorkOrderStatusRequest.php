<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Http\Requests;

use App\Domains\WorkCore\System\Verticals\Codebooks\VerticalValidationRules;
use Illuminate\Foundation\Http\FormRequest;

final class ChangeWorkOrderStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(VerticalValidationRules $vertical): array
    {
        return [
            'status' => ['required', $vertical->rule('work_order.status', [
                'draft', 'ready', 'in_progress', 'paused', 'completed', 'cancelled',
            ])],
            'reason' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

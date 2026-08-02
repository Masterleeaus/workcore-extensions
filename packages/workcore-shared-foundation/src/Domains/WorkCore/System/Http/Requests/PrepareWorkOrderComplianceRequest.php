<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PrepareWorkOrderComplianceRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        $routeWorkOrder = $this->route('workOrder');
        if (is_string($routeWorkOrder) && $routeWorkOrder !== '') {
            $this->merge(['work_order_public_id' => $routeWorkOrder]);
        }
    }
    public function rules(): array { return ['work_order_public_id' => ['required', 'string', 'size:26']]; }
}

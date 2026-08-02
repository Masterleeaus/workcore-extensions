<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class RecordWorkOrderSafetyControlRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void
    {
        $routeWorkOrder = $this->route('workOrder');
        if (is_string($routeWorkOrder) && $routeWorkOrder !== '') {
            $this->merge(['work_order_public_id' => $routeWorkOrder]);
        }
    }
    public function rules(): array { return [
        'work_order_public_id'=>['required','string','size:26'], 'requirement_code'=>['nullable','string','max:120'],
        'worker_public_id'=>['nullable','string','size:26'], 'control_type'=>['required','string','max:100'],
        'status'=>['nullable','in:pending,completed,failed,not_required'], 'completed_at'=>['nullable','date'],
        'details'=>['nullable','array'], 'notes'=>['nullable','string','max:10000'],
    ]; }
}

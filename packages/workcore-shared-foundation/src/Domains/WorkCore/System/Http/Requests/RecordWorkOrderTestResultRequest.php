<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class RecordWorkOrderTestResultRequest extends FormRequest
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
        'worker_public_id'=>['nullable','string','size:26'], 'trade_licence_public_id'=>['nullable','string','size:26'],
        'calibration_credential_public_id'=>['nullable','string','size:26'], 'test_type'=>['required','string','max:120'],
        'result'=>['required','in:pass,fail,advisory,not_applicable'], 'numeric_value'=>['nullable','numeric'],
        'unit'=>['nullable','string','max:40'], 'readings'=>['nullable','array'],
        'instrument_identifier'=>['nullable','string','max:160'], 'tested_at'=>['nullable','date'],
        'notes'=>['nullable','string','max:10000'],
    ]; }
}

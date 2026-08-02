<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class RecordWorkOrderPermitRequest extends FormRequest
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
        'permit_type'=>['required','string','max:100'], 'permit_number'=>['nullable','string','max:160'],
        'authority'=>['nullable','string','max:200'], 'jurisdiction'=>['nullable','string','max:80'],
        'status'=>['nullable','in:requested,approved,rejected,expired,revoked'], 'issued_at'=>['nullable','date'],
        'expires_at'=>['nullable','date','after_or_equal:issued_at'], 'conditions'=>['nullable','array'],
        'document_path'=>['nullable','string','max:500'],
    ]; }
}

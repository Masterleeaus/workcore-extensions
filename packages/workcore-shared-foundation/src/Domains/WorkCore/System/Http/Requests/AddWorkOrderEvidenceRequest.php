<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class AddWorkOrderEvidenceRequest extends FormRequest
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
        'worker_public_id'=>['nullable','string','size:26'], 'form_submission_public_id'=>['nullable','string','size:26'],
        'evidence_type'=>['required','string','max:80'], 'phase'=>['nullable','in:before,during,after,defect,certificate,warranty'],
        'storage_path'=>['nullable','string','max:500'], 'content_hash'=>['nullable','string','max:128'],
        'captured_at'=>['nullable','date'], 'metadata'=>['nullable','array'],
    ]; }
}

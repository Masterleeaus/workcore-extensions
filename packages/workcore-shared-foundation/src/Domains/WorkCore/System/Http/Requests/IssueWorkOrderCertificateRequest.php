<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IssueWorkOrderCertificateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'work_order_public_id' => ['required', 'string', 'size:26'],
            'worker_public_id' => ['required', 'string', 'size:26'],
            'trade_licence_public_id' => ['required', 'string', 'size:26'],
            'business_line_public_id' => ['nullable', 'string', 'size:26'],
            'vertical_key' => ['required', 'in:plumbing,electrical'],
            'certificate_type' => ['required', 'string', 'max:100'],
            'certificate_number' => ['required', 'string', 'max:160'],
            'issued_at' => ['nullable', 'date', 'before_or_equal:now'],
            'test_results' => ['required', 'array', 'min:1'],
            'declaration' => ['nullable', 'array'],
            'document_path' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LinkAssetDocumentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'file_reference' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'in:manual,photo,warranty,service_evidence,calibration_certificate,invoice,receipt,disposal,evidence,other'],
            'related_type' => ['nullable', 'string', 'max:80'],
            'related_public_id' => ['nullable', 'string', 'size:26'],
            'title' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

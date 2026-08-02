<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCrmActivityRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'subject_type' => ['required', 'in:lead,customer,contact,opportunity'],
            'subject_public_id' => ['required', 'string', 'size:26'],
            'type' => ['sometimes', 'in:note,call,email,meeting,task,system'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'direction' => ['nullable', 'in:inbound,outbound,internal'],
            'scheduled_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

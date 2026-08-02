<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAccommodationHousekeepingRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:assigned,in_progress,cleaned,inspected,ready,blocked,cancelled,dirty'],
            'assigned_worker_public_id' => ['nullable', 'string', 'size:26'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'due_at' => ['nullable', 'date'],
            'condition_before' => ['nullable', 'string', 'max:80'],
            'condition_after' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

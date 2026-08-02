<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StartAssetMaintenanceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'worker_public_id' => ['nullable', 'string', 'size:26'],
            'started_at' => ['nullable', 'date'],
            'downtime_started_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string'],
        ];
    }
}

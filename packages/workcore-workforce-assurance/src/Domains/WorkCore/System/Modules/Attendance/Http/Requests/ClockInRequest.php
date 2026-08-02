<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ClockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worker_public_id' => ['required', 'string', 'size:26'],
            'roster_shift_public_id' => ['nullable', 'string', 'size:26'],
            'work_order_public_id' => ['nullable', 'string', 'size:26'],
            'premises_public_id' => ['nullable', 'string', 'size:26'],
            'verification_attempt_public_id' => ['nullable', 'string', 'size:26'],
            'clocked_in_at' => ['nullable', 'date'],
            'timezone' => ['nullable', 'timezone'],
            'source' => ['sometimes', 'in:manual,mobile,kiosk,roster,dispatch,api,geofence,static_qr,dynamic_qr,face,ip_address,offline,verified'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

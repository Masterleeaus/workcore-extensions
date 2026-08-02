<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Inventory\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class RecordStockMovementRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'movement_type' => ['required','in:receipt,transfer,adjustment,issue,return,consumption,waste'],
            'quantity' => ['required','numeric','not_in:0'],
            'from_location_public_id' => ['nullable','string','size:26'],
            'to_location_public_id' => ['nullable','string','size:26'],
            'work_order_public_id' => ['nullable','string','size:26'],
            'appointment_public_id' => ['nullable','string','size:26'],
            'worker_public_id' => ['nullable','string','size:26'],
            'unit_cost' => ['nullable','numeric','min:0'],
            'reference' => ['nullable','string','max:150'],
            'reason' => ['nullable','string'],
            'occurred_at' => ['nullable','date'],
            'metadata' => ['nullable','array'],
        ];
    }
}

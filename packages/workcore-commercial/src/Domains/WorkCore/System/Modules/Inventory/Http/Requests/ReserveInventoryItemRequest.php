<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Inventory\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class ReserveInventoryItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'location_public_id' => ['required','string','size:26'],
            'work_order_public_id' => ['nullable','string','size:26','required_without:appointment_public_id'],
            'appointment_public_id' => ['nullable','string','size:26','required_without:work_order_public_id'],
            'quantity' => ['required','numeric','gt:0'],
            'unit_cost' => ['nullable','numeric','min:0'],
            'reserved_until' => ['nullable','date'],
            'notes' => ['nullable','string'],
        ];
    }
}

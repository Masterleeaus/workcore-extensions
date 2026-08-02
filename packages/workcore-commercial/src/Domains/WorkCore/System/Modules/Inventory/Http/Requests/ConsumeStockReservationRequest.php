<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Inventory\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class ConsumeStockReservationRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'quantity' => ['required','numeric','gt:0'],
            'worker_public_id' => ['nullable','string','size:26'],
            'unit_cost' => ['nullable','numeric','min:0'],
            'reason' => ['nullable','string'],
            'occurred_at' => ['nullable','date'],
            'metadata' => ['nullable','array'],
        ];
    }
}

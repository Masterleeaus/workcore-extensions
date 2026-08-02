<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreAssetMeterReadingRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'worker_public_id' => ['nullable','string','size:26'],
            'reading_type' => ['required','string','max:60'],
            'reading_value' => ['required','numeric'],
            'unit' => ['required','string','max:40'],
            'read_at' => ['nullable','date'],
            'source' => ['sometimes','in:manual,device,integration,inspection,work_order'],
            'notes' => ['nullable','string'],
            'metadata' => ['nullable','array'],
        ];
    }
}

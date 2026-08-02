<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Supply\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return [
        'location_public_id'=>['required','string','size:26'],'stock_count_public_id'=>['nullable','string','size:26'],'adjustment_number'=>['required','string','max:100'],
        'reason_code'=>['required','string','max:60'],'reason'=>['nullable','string','max:5000'],'items'=>['required','array','min:1'],
        'items.*.item_public_id'=>['required','string','size:26'],'items.*.batch_public_id'=>['nullable','string','size:26'],'items.*.quantity_delta'=>['required','numeric','not_in:0'],'items.*.unit_cost'=>['nullable','numeric','min:0'],'items.*.notes'=>['nullable','string','max:5000'],
    ];}
}

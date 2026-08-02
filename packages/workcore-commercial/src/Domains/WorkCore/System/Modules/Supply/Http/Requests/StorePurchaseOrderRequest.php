<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Supply\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return [
        'supplier_public_id'=>['required','string','size:26'],'delivery_location_public_id'=>['nullable','string','size:26'],'order_number'=>['required','string','max:100'],
        'currency'=>['nullable','string','size:3'],'ordered_on'=>['nullable','date'],'expected_on'=>['nullable','date','after_or_equal:ordered_on'],
        'supplier_reference'=>['nullable','string','max:5000'],'notes'=>['nullable','string','max:5000'],'items'=>['required','array','min:1'],
        'items.*.item_public_id'=>['nullable','string','size:26'],'items.*.description'=>['required','string','max:255'],'items.*.quantity'=>['required','numeric','gt:0'],
        'items.*.unit_cost'=>['required','numeric','min:0'],'items.*.tax_rate'=>['nullable','numeric','between:0,100'],'items.*.metadata'=>['nullable','array'],
    ];}
}

<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Supply\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class ReceiveGoodsRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return [
        'purchase_order_public_id'=>['nullable','string','size:26'],'supplier_public_id'=>['nullable','string','size:26'],'location_public_id'=>['required','string','size:26'],
        'receipt_number'=>['required','string','max:100'],'received_at'=>['nullable','date'],'delivery_reference'=>['nullable','string','max:160'],
        'evidence_file_references'=>['nullable','array'],'evidence_file_references.*'=>['string','max:255'],'notes'=>['nullable','string','max:5000'],'items'=>['required','array','min:1'],
        'items.*.item_public_id'=>['required','string','size:26'],'items.*.purchase_order_item_public_id'=>['nullable','string','size:26'],
        'items.*.accepted_quantity'=>['required','numeric','min:0'],'items.*.rejected_quantity'=>['nullable','numeric','min:0'],'items.*.unit_cost'=>['nullable','numeric','min:0'],
        'items.*.batch_number'=>['nullable','string','max:150'],'items.*.serial_number'=>['nullable','string','max:150'],'items.*.expires_on'=>['nullable','date'],
        'items.*.condition'=>['nullable','string','max:40'],'items.*.rejection_reason'=>['nullable','string','max:5000'],'items.*.create_asset'=>['sometimes','boolean'],'items.*.metadata'=>['nullable','array'],
    ];}
}

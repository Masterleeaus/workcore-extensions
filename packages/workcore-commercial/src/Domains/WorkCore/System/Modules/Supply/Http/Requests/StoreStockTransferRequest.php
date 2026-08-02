<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Supply\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreStockTransferRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return [
        'from_location_public_id'=>['required','string','size:26'],'to_location_public_id'=>['required','string','size:26','different:from_location_public_id'],
        'transfer_number'=>['required','string','max:100'],'notes'=>['nullable','string','max:5000'],'items'=>['required','array','min:1'],
        'items.*.item_public_id'=>['required','string','size:26'],'items.*.batch_public_id'=>['nullable','string','size:26'],'items.*.quantity'=>['required','numeric','gt:0'],
    ];}
}

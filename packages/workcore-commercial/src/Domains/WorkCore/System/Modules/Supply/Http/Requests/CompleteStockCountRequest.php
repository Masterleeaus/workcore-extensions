<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Supply\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class CompleteStockCountRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return ['items'=>['required','array','min:1'],'items.*.item_public_id'=>['required','string','size:26'],'items.*.batch_public_id'=>['nullable','string','size:26'],'items.*.counted_quantity'=>['required','numeric','min:0'],'items.*.variance_reason'=>['nullable','string','max:5000']];}
}

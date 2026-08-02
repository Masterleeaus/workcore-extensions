<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Supply\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreStockCountRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return ['location_public_id'=>['required','string','size:26'],'count_number'=>['required','string','max:100'],'count_type'=>['sometimes','in:cycle,full,spot'],'scheduled_at'=>['nullable','date'],'notes'=>['nullable','string','max:5000']];}
}

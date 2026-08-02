<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Supply\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreInventoryCategoryRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return ['parent_public_id'=>['nullable','string','size:26'],'code'=>['required','string','max:100'],'name'=>['required','string','max:180'],'description'=>['nullable','string','max:5000'],'active'=>['sometimes','boolean']];}
}

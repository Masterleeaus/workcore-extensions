<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Supply\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class RateSupplierRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return ['quality_rating'=>['required','integer','between:1,5'],'delivery_rating'=>['required','integer','between:1,5'],'service_rating'=>['required','integer','between:1,5'],'notes'=>['nullable','string','max:5000'],'rated_at'=>['nullable','date']];}
}

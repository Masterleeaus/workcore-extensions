<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Supply\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreSupplierRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return [
        'supplier_number'=>['required','string','max:100'],'name'=>['required','string','max:200'],'status'=>['sometimes','in:active,inactive,suspended'],
        'contact_name'=>['nullable','string','max:180'],'email'=>['nullable','email','max:180'],'phone'=>['nullable','string','max:80'],'website'=>['nullable','url','max:255'],
        'tax_identifier'=>['nullable','string','max:100'],'payment_terms'=>['nullable','string','max:100'],'currency'=>['nullable','string','size:3'],
        'address'=>['nullable','string','max:5000'],'capabilities'=>['nullable','array'],'metadata'=>['nullable','array'],
    ];}
}

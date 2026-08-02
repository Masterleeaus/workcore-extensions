<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StorePremisesAccessPointRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return [
        'space_public_id'=>['nullable','string','size:26'],'asset_public_id'=>['nullable','string','size:26'],
        'name'=>['required','string','max:180'],'access_type'=>['sometimes','string','in:entry,exit,key,gate,garage,lift,restricted_area'],
        'vault_secret_reference'=>['nullable','string','max:255'],'availability_status'=>['sometimes','string','in:available,unavailable,restricted,maintenance'],
        'instructions'=>['nullable','array'],'metadata'=>['nullable','array'],
        'access_code'=>['prohibited'],'secret'=>['prohibited'],
    ];}
}

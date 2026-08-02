<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StorePremisesSpaceRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return [
        'parent_public_id'=>['nullable','string','size:26'],'space_type'=>['required','string','max:40'],
        'code'=>['nullable','string','max:100'],'name'=>['required','string','max:200'],'floor_label'=>['nullable','string','max:100'],
        'area_square_metres'=>['nullable','numeric','min:0'],'capacity'=>['nullable','integer','min:0'],'status'=>['sometimes','string','in:active,inactive,restricted,closed'],
        'service_attributes'=>['nullable','array'],'notes'=>['nullable','string','max:5000'],
    ];}
}

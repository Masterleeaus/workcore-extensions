<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StorePremisesRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return ['customer_public_id'=>['required','string','size:26'],'name'=>['required','string','max:255'],'address_line_1'=>['required','string','max:255'],'address_line_2'=>['nullable','string','max:255'],'suburb'=>['nullable','string','max:150'],'state'=>['nullable','string','max:100'],'postcode'=>['nullable','string','max:20'],'country_code'=>['nullable','string','size:2'],'latitude'=>['nullable','numeric','between:-90,90'],'longitude'=>['nullable','numeric','between:-180,180'],'access_instructions'=>['nullable','string','max:5000'],'parking_instructions'=>['nullable','string','max:5000'],'hazards'=>['nullable','string','max:5000'],'service_requirements'=>['nullable','string','max:5000'],'is_primary'=>['sometimes','boolean']];}
}

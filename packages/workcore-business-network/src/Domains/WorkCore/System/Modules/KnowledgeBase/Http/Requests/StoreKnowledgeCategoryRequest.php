<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\KnowledgeBase\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreKnowledgeCategoryRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return ['parent_public_id'=>['nullable','string','size:26'],'name'=>['required','string','max:160'],'slug'=>['nullable','string','max:180'],'description'=>['nullable','string'],'visibility'=>['sometimes','in:internal,customer,resident,owner,contractor,public'],'sort_order'=>['sometimes','integer','min:0'],'active'=>['sometimes','boolean']];}
}

<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StorePremisesHazardRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return [
        'space_public_id'=>['nullable','string','size:26'],'hazard_type'=>['required','string','max:80'],
        'severity'=>['sometimes','string','in:low,medium,high,critical'],'description'=>['required','string','max:10000'],
        'control_measures'=>['nullable','string','max:10000'],'required_credential_public_ids'=>['nullable','array'],
        'required_credential_public_ids.*'=>['string','size:26'],'evidence_file_references'=>['nullable','array'],
        'evidence_file_references.*'=>['string','max:255'],'review_due_on'=>['nullable','date'],
    ];}
}

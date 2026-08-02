<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StorePremisesServiceWindowRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return [
        'space_public_id'=>['nullable','string','size:26'],'day_of_week'=>['required','integer','between:0,6'],
        'starts_at'=>['required','date_format:H:i'],'ends_at'=>['required','date_format:H:i'],
        'timezone'=>['nullable','timezone'],'window_type'=>['sometimes','string','max:40'],'active'=>['sometimes','boolean'],
        'effective_from'=>['nullable','date'],'effective_until'=>['nullable','date','after_or_equal:effective_from'],'notes'=>['nullable','string','max:5000'],
    ];}
}

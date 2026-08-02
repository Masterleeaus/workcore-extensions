<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StorePremisesMeterReadingRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return [
        'space_public_id'=>['nullable','string','size:26'],'asset_public_id'=>['nullable','string','size:26'],
        'meter_reference'=>['required','string','max:120'],'reading_type'=>['required','string','max:60'],
        'reading_value'=>['required','numeric'],'unit'=>['required','string','max:40'],'read_at'=>['nullable','date'],
        'source'=>['sometimes','string','max:40'],'evidence_file_references'=>['nullable','array'],'evidence_file_references.*'=>['string','max:255'],
    ];}
}

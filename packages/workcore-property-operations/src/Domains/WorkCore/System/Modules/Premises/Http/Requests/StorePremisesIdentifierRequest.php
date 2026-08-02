<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StorePremisesIdentifierRequest extends FormRequest
{
    public function authorize(): bool{return true;}
    public function rules(): array{return [
        'space_public_id'=>['nullable','string','size:26'],'identifier_type'=>['required','string','in:qr,barcode,nfc,external'],
        'value'=>['required','string','max:255'],'active'=>['sometimes','boolean'],'metadata'=>['nullable','array'],
    ];}
}

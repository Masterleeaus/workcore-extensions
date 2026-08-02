<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class LinkPremisesDocumentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'space_public_id'=>['nullable','string','size:26'],
        'file_reference'=>['required','string','max:255'],
        'document_type'=>['required','string','max:50'],
        'title'=>['nullable','string','max:255'],
        'metadata'=>['nullable','array'],
    ]; }
}

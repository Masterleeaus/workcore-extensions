<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managedpremises.keys.create') ?? false;
    }

    public function rules(): array
    {
        return ['type'=>['required','string','max:40'],'location'=>['nullable','string','max:190'],'code'=>['nullable','string','max:120'],'notes'=>['nullable','string','max:2000']];
    }
}

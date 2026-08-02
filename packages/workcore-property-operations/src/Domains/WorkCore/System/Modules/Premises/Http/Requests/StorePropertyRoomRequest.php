<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managedpremises.update') ?? false;
    }

    public function rules(): array
    {
        return ['name'=>['required','string','max:120'],'type'=>['nullable','string','max:60'],'notes'=>['nullable','string','max:2000']];
    }
}

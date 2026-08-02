<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managedpremises.update') ?? false;
    }

    public function rules(): array
    {
        return ['label'=>['required','string','max:190'],'category'=>['nullable','string','max:80'],'serial'=>['nullable','string','max:120'],'location'=>['nullable','string','max:190'],'notes'=>['nullable','string','max:5000']];
    }
}

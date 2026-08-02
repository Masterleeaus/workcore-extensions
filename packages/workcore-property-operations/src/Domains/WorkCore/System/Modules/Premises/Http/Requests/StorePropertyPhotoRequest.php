<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managedpremises.photos.create') ?? false;
    }

    public function rules(): array
    {
        return ['photo'=>['required','file','mimes:jpg,jpeg,png,webp','max:10240'],'caption'=>['nullable','string','max:190']];
    }
}

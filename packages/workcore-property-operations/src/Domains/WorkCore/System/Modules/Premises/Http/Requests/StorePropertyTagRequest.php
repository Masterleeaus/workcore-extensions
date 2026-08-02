<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managedpremises.update') ?? false;
    }

    public function rules(): array
    {
        return ['tag'=>['required','string','max:60']];
    }
}

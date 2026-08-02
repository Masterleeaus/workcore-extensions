<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyServiceWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managedpremises.update') ?? false;
    }

    public function rules(): array
    {
        return ['days'=>['nullable','string','max:50'],'time_from'=>['nullable','string','max:10'],'time_to'=>['nullable','string','max:10'],'notes'=>['nullable','string','max:2000']];
    }
}

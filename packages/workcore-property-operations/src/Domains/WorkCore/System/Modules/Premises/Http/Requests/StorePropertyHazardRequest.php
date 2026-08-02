<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyHazardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managedpremises.update') ?? false;
    }

    public function rules(): array
    {
        return ['hazard'=>['required','string','max:190'],'risk_level'=>['nullable','string','max:30'],'controls'=>['nullable','string','max:5000']];
    }
}

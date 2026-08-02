<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managedpremises.units.create') ?? false;
    }

    public function rules(): array
    {
        return ['label' => ['required','string','max:120'], 'unit_number' => ['nullable','string','max:50'], 'level' => ['nullable','string','max:50']];
    }
}

<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managedpremises.checklists.create') ?? false;
    }

    public function rules(): array
    {
        return ['type'=>['required','string','max:60'],'title'=>['required','string','max:190'],'items'=>['required','array','min:1'],'items.*.label'=>['required','string','max:190']];
    }
}

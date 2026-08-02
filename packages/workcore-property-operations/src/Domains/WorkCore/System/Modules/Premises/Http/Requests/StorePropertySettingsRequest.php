<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managedpremises.settings') ?? false;
    }

    public function rules(): array
    {
        return ['default_job_status'=>['nullable','string','max:40'],'require_access_notes'=>['nullable','boolean'],'enable_unit_tracking'=>['nullable','boolean']];
    }
}

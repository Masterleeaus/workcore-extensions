<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return function_exists('user') ? user()->permission('managedpremises.create') !== 'none' : true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'property_code' => ['nullable', 'string', 'max:100'],
            'type' => ['required', 'string', 'max:60'],
            'profile_key' => ['nullable', 'string', 'max:60'],
            'terminology_overrides' => ['nullable', 'array'],
            'terminology_overrides.*' => ['nullable', 'string', 'max:80'],
            'feature_overrides' => ['nullable', 'array'],
            'feature_overrides.*' => ['boolean'],
            'profile_attributes' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'max:30'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'suburb' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:255'],
            'access_notes' => ['nullable', 'string'],
            'hazards' => ['nullable', 'string'],
            'parking_notes' => ['nullable', 'string', 'max:255'],
            'lockbox_code' => ['nullable', 'string', 'max:255'],
            'keys_location' => ['nullable', 'string', 'max:255'],
            'primary_contact_name' => ['nullable', 'string', 'max:255'],
            'primary_contact_phone' => ['nullable', 'string', 'max:50'],
            'primary_contact_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreFormTemplateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $fieldRules = [
            'field_key' => ['required','string','max:120'],
            'label' => ['required','string','max:240'],
            'field_type' => ['required','in:text,textarea,number,boolean,date,datetime,select,multiselect,signature,photo,file,pass_fail,rating,meter_reading'],
            'is_required' => ['sometimes','boolean'],
            'sort_order' => ['sometimes','integer','min:0'],
            'help_text' => ['nullable','string'],
            'options' => ['nullable','array'],
            'validation_rules' => ['nullable','array'],
            'failure_action' => ['sometimes','in:none,flag,create_work_order'],
            'failure_severity' => ['sometimes','in:low,medium,high,critical,life_safety'],
        ];
        $rules = [
            'code' => ['required','string','max:100'],
            'name' => ['required','string','max:200'],
            'form_type' => ['sometimes','in:checklist,inspection,condition_report,application,consent,survey,audit,job_form,incident,custom'],
            'version' => ['sometimes','integer','min:1'],
            'applies_to' => ['sometimes','in:general,customer,premises,work_order,appointment,asset,worker,roster_shift'],
            'description' => ['nullable','string'],
            'settings' => ['nullable','array'],
            'active' => ['sometimes','boolean'],
            'sections' => ['nullable','array'],
            'sections.*.title' => ['required_with:sections','string','max:200'],
            'sections.*.instructions' => ['nullable','string'],
            'sections.*.sort_order' => ['sometimes','integer','min:0'],
            'sections.*.fields' => ['nullable','array'],
            'fields' => ['nullable','array'],
        ];
        foreach ($fieldRules as $key => $rule) {
            $rules["sections.*.fields.*.{$key}"] = $rule;
            $rules["fields.*.{$key}"] = $rule;
        }
        return $rules;
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssignAssetRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'assignment_type' => ['required', 'in:worker,team,vehicle,premise,premise_zone,job,storage_location,asset,installed_at,located_at,issued_to,custodian,mounted_on,stored_in'],
            'allocation_mode' => ['sometimes', 'in:assignment,loan,checkout,transfer'],
            'worker_public_id' => ['nullable', 'string', 'size:26'],
            'team_public_id' => ['nullable', 'string', 'size:26'],
            'vehicle_public_id' => ['nullable', 'string', 'size:26'],
            'premises_public_id' => ['nullable', 'string', 'size:26'],
            'premise_zone_public_id' => ['nullable', 'string', 'size:26'],
            'job_public_id' => ['nullable', 'string', 'size:26'],
            'storage_location_public_id' => ['nullable', 'string', 'size:26'],
            'target_asset_public_id' => ['nullable', 'string', 'size:26'],
            'custodian_user_id' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expected_return_at' => ['nullable', 'date', 'after:starts_at'],
            'acknowledged_by_user_id' => ['nullable', 'integer', 'min:1'],
            'acknowledged_at' => ['nullable', 'date'],
            'condition_out' => ['nullable', 'array'],
            'condition_out.grade' => ['required_with:condition_out', 'in:new,excellent,good,fair,poor,critical,unknown'],
            'condition_out.summary' => ['nullable', 'string'],
            'condition_out.measurements' => ['nullable', 'array'],
            'condition_out.evidence_file_references' => ['nullable', 'array'],
            'evidence_file_references' => ['nullable', 'array'],
            'credential_requirements' => ['nullable', 'array'],
            'credential_requirements.required_skill_public_ids' => ['nullable', 'array'],
            'credential_requirements.required_skill_public_ids.*' => ['string', 'size:26'],
            'credential_requirements.required_skill_codes' => ['nullable', 'array'],
            'credential_requirements.required_skill_codes.*' => ['string', 'max:100'],
            'credential_requirements.required_certification_public_ids' => ['nullable', 'array'],
            'credential_requirements.required_certification_public_ids.*' => ['string', 'size:26'],
            'credential_requirements.require_verified_skills' => ['sometimes', 'boolean'],
            'credential_requirements.require_unexpired_certifications' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

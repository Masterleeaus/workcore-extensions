<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpsertNDISPlanRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'plan_number' => ['required','string','max:100'], 'status' => ['nullable','in:draft,active,superseded,expired,cancelled'],
            'starts_on' => ['required','date'], 'ends_on' => ['required','date','after_or_equal:starts_on'], 'review_on' => ['nullable','date'],
            'plan_manager_contact_public_id' => ['nullable','string','size:26'],
            'funding_management' => ['nullable','in:agency_managed,plan_managed,self_managed,mixed,unknown'],
            'currency' => ['nullable','string','size:3'], 'total_budget' => ['nullable','numeric','min:0'], 'notes' => ['nullable','string','max:10000'],
            'metadata' => ['nullable','array'], 'budgets' => ['nullable','array','max:100'],
            'budgets.*.public_id' => ['nullable','string','size:26'], 'budgets.*.category_code' => ['required_with:budgets','string','max:80'],
            'budgets.*.category_name' => ['required_with:budgets','string','max:191'], 'budgets.*.support_purpose' => ['nullable','in:core,capacity_building,capital,other'],
            'budgets.*.allocated_amount' => ['nullable','numeric','min:0'], 'budgets.*.committed_amount' => ['nullable','numeric','min:0'],
            'budgets.*.spent_amount' => ['nullable','numeric','min:0'], 'budgets.*.flexible' => ['nullable','boolean'], 'budgets.*.metadata' => ['nullable','array'],
        ];
    }
}

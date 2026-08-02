<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Assets\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class StoreAssetMaintenanceRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'maintenance_plan_public_id' => ['nullable','string','size:26'],
            'work_order_public_id' => ['nullable','string','size:26'],
            'appointment_public_id' => ['nullable','string','size:26'],
            'worker_public_id' => ['nullable','string','size:26'],
            'reference' => ['nullable','string','max:120'],
            'maintenance_type' => ['required','in:inspection,test,audit,service,preventive,repair,calibration,replacement,cleaning'],
            'status' => ['sometimes','in:due,scheduled,in_progress,awaiting_parts,completed,cancelled,failed'],
            'priority' => ['sometimes','in:low,normal,high,urgent,critical'],
            'due_at' => ['nullable','date'],
            'vendor_name' => ['nullable','string','max:200'],
            'scheduled_for' => ['nullable','date'],
            'started_at' => ['nullable','date'],
            'completed_at' => ['nullable','date','after_or_equal:started_at'],
            'downtime_started_at' => ['nullable','date'],
            'downtime_ended_at' => ['nullable','date'],
            'cost' => ['nullable','numeric','min:0'],
            'currency' => ['nullable','string','size:3'],
            'findings' => ['nullable','string'],
            'root_cause' => ['nullable','string'],
            'actions_taken' => ['nullable','string'],
            'parts_references' => ['nullable','array'],
            'next_due_on' => ['nullable','date'],
            'evidence_file_references' => ['nullable','array'],
            'return_to_service_approved_by_user_id' => ['nullable','integer','min:1'],
            'metadata' => ['nullable','array'],
            'notes' => ['nullable','string'],
        ];
    }
}

<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Repairs\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class ReportRepairOrderRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return ['asset_public_id'=>['required','string','size:26'],'vehicle_public_id'=>['nullable','string','size:26'],'template_public_id'=>['nullable','string','size:26'],'work_order_public_id'=>['nullable','string','size:26'],'assigned_worker_public_id'=>['nullable','string','size:26'],'repair_number'=>['required','string','max:100'],'priority'=>['sometimes','in:low,normal,high,urgent,critical'],'fault_description'=>['required','string','max:10000'],'estimated_cost'=>['nullable','numeric','min:0'],'reported_at'=>['nullable','date'],'make_unavailable'=>['sometimes','boolean'],'evidence_file_references'=>['nullable','array'],'evidence_file_references.*'=>['string','max:255']];}
}

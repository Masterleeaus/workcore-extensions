<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Fleet\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class AssignFleetVehicleRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return ['worker_public_id'=>['nullable','string','size:26'],'work_order_public_id'=>['nullable','string','size:26'],'appointment_public_id'=>['nullable','string','size:26'],'assignment_type'=>['sometimes','in:worker,job,appointment,route'],'starts_at'=>['nullable','date'],'expected_return_at'=>['nullable','date','after:starts_at'],'notes'=>['nullable','string','max:5000']];}
}

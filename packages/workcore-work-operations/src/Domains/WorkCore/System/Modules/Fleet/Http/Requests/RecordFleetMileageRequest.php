<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Fleet\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class RecordFleetMileageRequest extends FormRequest
{
    public function authorize():bool{return true;}
    public function rules():array{return ['worker_public_id'=>['nullable','string','size:26'],'work_order_public_id'=>['nullable','string','size:26'],'appointment_public_id'=>['nullable','string','size:26'],'odometer_start'=>['nullable','numeric','min:0'],'odometer_end'=>['required','numeric','min:0'],'logged_at'=>['nullable','date'],'source'=>['sometimes','string','max:40'],'notes'=>['nullable','string','max:5000'],'evidence_file_references'=>['nullable','array'],'evidence_file_references.*'=>['string','max:255']];}
}
